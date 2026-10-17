package com.minthantzintun.trypost.feature.edit

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.model.PlatformText
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.text
import com.minthantzintun.trypost.core.model.toPlainText
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** Where a server validation message belongs on the edit screen. */
sealed interface EditField {
    data object Content : EditField

    data class Text(val platformId: String, val text: PlatformText) : EditField
}

data class EditPostUiState(
    val post: Post? = null,
    val content: String = "",
    /** Each Post platform's texts, keyed by Post platform id. */
    val texts: Map<String, Map<PlatformText, String>> = emptyMap(),
    val isLoading: Boolean = true,
    val isSaving: Boolean = false,
    val loadError: ApiError? = null,
    /** A save error that no field shows: a 409, a lost connection, or a message without a known field. */
    val saveError: ApiError? = null,
    val fieldErrors: Map<EditField, String> = emptyMap(),
    val isSaved: Boolean = false,
)

/**
 * Edits a Draft's or Scheduled post's content and each Social account's own
 * texts, offering only the texts its Content type publishes.
 */
@HiltViewModel
class EditPostViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val postsRepository: PostsRepository,
) : ViewModel() {

    private val postId: String = checkNotNull(savedStateHandle["id"]) { "EditPostDestination needs an id" }

    private val _state = MutableStateFlow(EditPostUiState())
    val state: StateFlow<EditPostUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun retry() {
        load()
    }

    fun changeContent(value: String) {
        _state.update { it.copy(content = value, fieldErrors = it.fieldErrors - EditField.Content) }
    }

    fun changeText(platformId: String, text: PlatformText, value: String) {
        _state.update {
            val platformTexts = it.texts[platformId].orEmpty() + (text to value)
            it.copy(texts = it.texts + (platformId to platformTexts), fieldErrors = it.fieldErrors - EditField.Text(platformId, text))
        }
    }

    fun save() {
        val current = _state.value
        val post = current.post ?: return
        if (current.isSaving) {
            return
        }

        _state.update { it.copy(isSaving = true, saveError = null, fieldErrors = emptyMap()) }

        viewModelScope.launch {
            val result = postsRepository.updateTexts(
                id = postId,
                // An untouched content goes back as stored, so an older Post's HTML is not rewritten.
                content = if (current.content == post.content.toPlainText()) post.content else current.content,
                platformOrder = post.platforms.map { it.id },
                texts = current.texts,
            )

            when (result) {
                is ApiResult.Success -> _state.update { it.copy(post = result.value, isSaving = false, isSaved = true) }
                is ApiResult.Failure -> _state.update { it.copy(isSaving = false).withSaveError(result.error, post) }
            }
        }
    }

    private fun load() {
        _state.update { it.copy(isLoading = true, loadError = null) }

        viewModelScope.launch {
            when (val result = postsRepository.post(postId)) {
                is ApiResult.Success -> _state.update { it.editing(result.value) }
                is ApiResult.Failure -> _state.update { it.copy(isLoading = false, loadError = result.error) }
            }
        }
    }

    private fun EditPostUiState.editing(post: Post): EditPostUiState = copy(
        post = post,
        // Older Posts may hold HTML; the app edits the content as plain text.
        content = post.content.toPlainText(),
        texts = post.platforms.associate { platform -> platform.id to platform.texts.associateWith { platform.text(it) } },
        isLoading = false,
        loadError = null,
    )

    private fun EditPostUiState.withSaveError(error: ApiError, post: Post): EditPostUiState {
        if (error !is ApiError.Validation) {
            return copy(saveError = error)
        }

        val fields = error.fields.mapNotNull { (key, messages) ->
            val field = key.toEditField(post) ?: return@mapNotNull null
            messages.firstOrNull()?.let { field to it }
        }.toMap()

        return copy(fieldErrors = fields, saveError = error.takeIf { fields.size < error.fields.size || fields.isEmpty() })
    }

    private fun String.toEditField(post: Post): EditField? {
        if (this == "content") {
            return EditField.Content
        }

        val match = platformMetaKey.matchEntire(this) ?: return null
        val platform = post.platforms.getOrNull(match.groupValues[1].toInt()) ?: return null
        val text = PlatformText.fromMetaKey(match.groupValues[2]) ?: return null

        return EditField.Text(platform.id, text)
    }

    private companion object {
        val platformMetaKey = Regex("""^platforms\.(\d+)\.meta\.([a-z_]+)(?:\.\d+)?$""")
    }
}
