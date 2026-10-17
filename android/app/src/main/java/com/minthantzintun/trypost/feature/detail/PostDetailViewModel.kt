package com.minthantzintun.trypost.feature.detail

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PostDetailUiState(
    val post: Post? = null,
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val error: ApiError? = null,
)

/**
 * One Post. While it is publishing it reloads every [POLL_INTERVAL_MS] and
 * stops once it settles, like the web app's `usePollWhile()`.
 */
@HiltViewModel
class PostDetailViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val postsRepository: PostsRepository,
) : ViewModel() {

    private val postId: String = checkNotNull(savedStateHandle["id"]) { "PostDetailDestination needs an id" }

    private val _state = MutableStateFlow(PostDetailUiState())
    val state: StateFlow<PostDetailUiState> = _state.asStateFlow()

    private var loadJob: Job? = null

    init {
        load(isRefresh = false)
    }

    fun refresh() {
        load(isRefresh = true)
    }

    fun retry() {
        load(isRefresh = false)
    }

    /** Shows a Post another screen just changed, without asking the server again. */
    fun show(post: Post) {
        _state.update { it.copy(post = post, error = null) }
    }

    private fun load(isRefresh: Boolean) {
        loadJob?.cancel()
        _state.update { it.copy(isLoading = !isRefresh && it.post == null, isRefreshing = isRefresh, error = null) }

        loadJob = viewModelScope.launch {
            while (true) {
                when (val result = postsRepository.post(postId)) {
                    is ApiResult.Success -> _state.update {
                        PostDetailUiState(post = result.value, isLoading = false, isRefreshing = false)
                    }
                    is ApiResult.Failure -> _state.update {
                        it.copy(isLoading = false, isRefreshing = false, error = result.error)
                    }
                }

                if (_state.value.post?.status != PostStatus.Publishing || _state.value.error != null) {
                    break
                }

                delay(POLL_INTERVAL_MS)
            }
        }
    }

    companion object {
        const val POLL_INTERVAL_MS = 5_000L
    }
}
