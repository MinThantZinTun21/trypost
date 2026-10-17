package com.minthantzintun.trypost.feature.videos

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.VideoSource
import com.minthantzintun.trypost.core.data.VideosRepository
import com.minthantzintun.trypost.core.model.Video
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** A picked video waiting for its name, and then uploading under it. */
data class PendingUpload(
    val source: VideoSource,
    val name: String,
)

/** The Videos list. `page` 0 means nothing has loaded yet. */
data class VideosUiState(
    val videos: List<Video> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 1,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val error: ApiError? = null,
    val loadMoreError: ApiError? = null,
    val pending: PendingUpload? = null,
    val isUploading: Boolean = false,
    val uploadProgress: Float = 0f,
    val uploadError: ApiError? = null,
    val deletingId: String? = null,
    val deleteError: ApiError? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val hasMore: Boolean get() = hasLoaded && page < lastPage
}

/**
 * Lists the Owner's Videos, uploads one picked from the Photo Picker and
 * deletes them. The upload runs while the app is open (no WorkManager).
 */
@HiltViewModel
class VideosViewModel @Inject constructor(
    private val videosRepository: VideosRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(VideosUiState())
    val state: StateFlow<VideosUiState> = _state.asStateFlow()

    private var listJob: Job? = null

    init {
        loadFirstPage(isRefresh = false)
    }

    fun refresh() {
        loadFirstPage(isRefresh = true)
    }

    fun retry() {
        loadFirstPage(isRefresh = false)
    }

    fun loadMore() {
        val current = _state.value
        if (!current.hasMore || current.isLoadingMore || current.isLoading || current.isRefreshing) {
            return
        }

        _state.update { it.copy(isLoadingMore = true, loadMoreError = null) }

        listJob = viewModelScope.launch {
            when (val result = videosRepository.page(current.page + 1)) {
                is ApiResult.Success -> _state.update { state ->
                    val knownIds = state.videos.mapTo(HashSet()) { it.id }
                    state.copy(
                        videos = state.videos + result.value.videos.filterNot { it.id in knownIds },
                        page = result.value.page,
                        lastPage = result.value.lastPage,
                        isLoadingMore = false,
                    )
                }
                is ApiResult.Failure -> _state.update { it.copy(isLoadingMore = false, loadMoreError = result.error) }
            }
        }
    }

    /** Asks for a name, starting from the file name without its extension. */
    fun pickVideo(source: VideoSource) {
        if (_state.value.isUploading) {
            return
        }

        val name = source.fileName.substringBeforeLast('.').trim().ifEmpty { source.fileName }
        _state.update { it.copy(pending = PendingUpload(source, name), uploadError = null, uploadProgress = 0f) }
    }

    fun updateName(name: String) {
        _state.update { state -> state.copy(pending = state.pending?.copy(name = name)) }
    }

    fun cancelUpload() {
        if (_state.value.isUploading) {
            return
        }

        _state.update { it.copy(pending = null, uploadError = null) }
    }

    fun upload(name: String) {
        val pending = _state.value.pending ?: return
        if (_state.value.isUploading) {
            return
        }

        _state.update {
            it.copy(pending = pending.copy(name = name), isUploading = true, uploadProgress = 0f, uploadError = null)
        }

        viewModelScope.launch {
            val result = videosRepository.upload(pending.source, name.trim()) { progress ->
                _state.update { it.copy(uploadProgress = progress) }
            }

            when (result) {
                is ApiResult.Success -> {
                    _state.update { it.copy(pending = null, isUploading = false, uploadProgress = 1f) }
                    loadFirstPage(isRefresh = true)
                }
                is ApiResult.Failure -> _state.update { it.copy(isUploading = false, uploadError = result.error) }
            }
        }
    }

    fun delete(id: String) {
        if (_state.value.deletingId != null) {
            return
        }

        _state.update { it.copy(deletingId = id, deleteError = null) }

        viewModelScope.launch {
            when (val result = videosRepository.delete(id)) {
                is ApiResult.Success -> _state.update { state ->
                    state.copy(videos = state.videos.filterNot { it.id == id }, deletingId = null)
                }
                is ApiResult.Failure -> _state.update { it.copy(deletingId = null, deleteError = result.error) }
            }
        }
    }

    fun dismissDeleteError() {
        _state.update { it.copy(deleteError = null) }
    }

    private fun loadFirstPage(isRefresh: Boolean) {
        listJob?.cancel()
        _state.update {
            it.copy(isLoading = !isRefresh, isRefreshing = isRefresh, isLoadingMore = false, error = null, loadMoreError = null)
        }

        listJob = viewModelScope.launch {
            when (val result = videosRepository.page(1)) {
                is ApiResult.Success -> _state.update {
                    it.copy(
                        videos = result.value.videos,
                        page = result.value.page,
                        lastPage = result.value.lastPage,
                        isLoading = false,
                        isRefreshing = false,
                    )
                }
                is ApiResult.Failure -> _state.update { it.copy(isLoading = false, isRefreshing = false, error = result.error) }
            }
        }
    }
}
