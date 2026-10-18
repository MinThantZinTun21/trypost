package com.minthantzintun.trypost.feature.detail

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.download.DownloadProgress
import com.minthantzintun.trypost.core.download.DownloadTracker
import com.minthantzintun.trypost.core.download.MediaDownloader
import com.minthantzintun.trypost.core.model.DownloadLink
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
import java.time.Instant
import javax.inject.Inject

data class PostDetailUiState(
    val post: Post? = null,
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val error: ApiError? = null,
    val isSaving: Boolean = false,
    val actionError: ApiError? = null,
    val isSendingToTelegram: Boolean = false,
    /** The server's word that the send was queued. */
    val telegramNotice: String? = null,
    val telegramError: ApiError? = null,
    val downloadOutcome: DownloadOutcome? = null,
)

/**
 * One Post. While it is publishing it reloads every [POLL_INTERVAL_MS] and
 * stops once it settles, like the web app's `usePollWhile()`.
 */
@HiltViewModel
class PostDetailViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val postsRepository: PostsRepository,
    private val downloader: MediaDownloader,
    private val downloadTracker: DownloadTracker,
) : ViewModel() {

    /** Progress of the app's running downloads, keyed by Download link. */
    val downloads: StateFlow<Map<String, DownloadProgress>> = downloadTracker.progress

    private val postId: String = checkNotNull(savedStateHandle["id"]) { "PostDetailDestination needs an id" }

    private val _state = MutableStateFlow(PostDetailUiState())
    val state: StateFlow<PostDetailUiState> = _state.asStateFlow()

    private var loadJob: Job? = null

    init {
        load(isRefresh = false)

        viewModelScope.launch {
            postsRepository.changes.collect { post ->
                if (post.id == postId) {
                    show(post)
                }
            }
        }
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

    fun reschedule(at: Instant) {
        runAction { postsRepository.reschedule(postId, at) }
    }

    fun publishNow() {
        runAction { postsRepository.publishNow(postId) }
    }

    /** Queues Send to Telegram; the Post itself does not change. */
    fun sendToTelegram() {
        if (_state.value.isSendingToTelegram) {
            return
        }

        _state.update { it.copy(isSendingToTelegram = true, telegramNotice = null, telegramError = null) }

        viewModelScope.launch {
            when (val result = postsRepository.sendToTelegram(postId)) {
                is ApiResult.Success -> _state.update { it.copy(isSendingToTelegram = false, telegramNotice = result.value) }
                is ApiResult.Failure -> _state.update { it.copy(isSendingToTelegram = false, telegramError = result.error) }
            }
        }
    }

    fun dismissTelegramNotice() {
        _state.update { it.copy(telegramNotice = null, telegramError = null) }
    }

    /** Saves one of the Post's media to Downloads. */
    fun download(link: DownloadLink) {
        val outcome = downloader.start(link)
        if (outcome == DownloadOutcome.Started) {
            downloadTracker.refresh()
        }
        _state.update { it.copy(downloadOutcome = outcome) }
    }

    fun dismissDownloadOutcome() {
        _state.update { it.copy(downloadOutcome = null) }
    }

    fun dismissActionError() {
        _state.update { it.copy(actionError = null) }
    }

    private fun runAction(action: suspend () -> ApiResult<Post>) {
        if (_state.value.isSaving) {
            return
        }

        _state.update { it.copy(isSaving = true, actionError = null) }

        viewModelScope.launch {
            when (val result = action()) {
                is ApiResult.Success -> {
                    _state.update { it.copy(post = result.value, isSaving = false, error = null) }

                    // Post now leaves it publishing, so follow it until it settles.
                    if (result.value.status == PostStatus.Publishing) {
                        load(isRefresh = false)
                    }
                }
                is ApiResult.Failure -> {
                    _state.update { it.copy(isSaving = false, actionError = result.error) }

                    // The Post moved on (it started publishing or finished), so show where it is now.
                    if (result.error is ApiError.Conflict) {
                        load(isRefresh = true)
                    }
                }
            }
        }
    }

    private fun load(isRefresh: Boolean) {
        loadJob?.cancel()
        _state.update { it.copy(isLoading = !isRefresh && it.post == null, isRefreshing = isRefresh, error = null) }

        loadJob = viewModelScope.launch {
            while (true) {
                when (val result = postsRepository.post(postId)) {
                    is ApiResult.Success -> _state.update {
                        it.copy(post = result.value, isLoading = false, isRefreshing = false, error = null)
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
