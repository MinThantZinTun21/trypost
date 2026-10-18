package com.minthantzintun.trypost.feature.videos

import android.text.format.Formatter
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.data.ContentUriVideoSource
import com.minthantzintun.trypost.core.data.VideoSource
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.model.DownloadLink
import com.minthantzintun.trypost.core.model.Video
import com.minthantzintun.trypost.core.ui.DownloadOutcomeSnackbar
import com.minthantzintun.trypost.core.ui.LoadMoreWhenNearEnd
import com.minthantzintun.trypost.core.ui.message
import com.minthantzintun.trypost.core.ui.rememberDownloadAction
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import kotlin.math.roundToInt
import kotlin.math.roundToLong

@Composable
fun VideosRoute(viewModel: VideosViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    VideosScreen(
        state = state,
        onPickVideo = viewModel::pickVideo,
        onNameChange = viewModel::updateName,
        onUpload = viewModel::upload,
        onCancelUpload = viewModel::cancelUpload,
        onDelete = viewModel::delete,
        onDownload = viewModel::download,
        onDownloadOutcomeShown = viewModel::dismissDownloadOutcome,
        onDismissDeleteError = viewModel::dismissDeleteError,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onLoadMore = viewModel::loadMore,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun VideosScreen(
    state: VideosUiState,
    onPickVideo: (VideoSource) -> Unit,
    onNameChange: (String) -> Unit,
    onUpload: (String) -> Unit,
    onCancelUpload: () -> Unit,
    onDelete: (String) -> Unit,
    onDownload: (DownloadLink) -> Unit,
    onDownloadOutcomeShown: () -> Unit,
    onDismissDeleteError: () -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
) {
    val context = LocalContext.current
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        uri?.let { onPickVideo(ContentUriVideoSource(context.contentResolver, it)) }
    }
    var confirmingDelete by remember { mutableStateOf<Video?>(null) }
    val snackbarHostState = remember { SnackbarHostState() }
    val download = rememberDownloadAction(snackbarHostState, onDownload)

    DownloadOutcomeSnackbar(state.downloadOutcome, snackbarHostState, onDownloadOutcomeShown)

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.videos_title), style = MaterialTheme.typography.titleLarge) },
                actions = {
                    TextButton(
                        onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly)) },
                        enabled = !state.isUploading,
                        modifier = Modifier.testTag("upload-video"),
                    ) {
                        Text(stringResource(R.string.videos_upload))
                    }
                },
            )
        },
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            VideoList(
                state = state,
                onRefresh = onRefresh,
                onRetry = onRetry,
                onLoadMore = onLoadMore,
                onDelete = { confirmingDelete = it },
                onDownload = download,
            )
        }
    }

    state.pending?.let { pending ->
        UploadDialog(
            name = pending.name,
            state = state,
            onNameChange = onNameChange,
            onUpload = onUpload,
            onCancel = onCancelUpload,
        )
    }

    confirmingDelete?.let { video ->
        AlertDialog(
            onDismissRequest = { confirmingDelete = null },
            title = { Text(stringResource(R.string.videos_delete_confirm_title)) },
            text = { Text(stringResource(R.string.videos_delete_confirm_body, video.name)) },
            confirmButton = {
                TextButton(
                    onClick = {
                        confirmingDelete = null
                        onDelete(video.id)
                    },
                    modifier = Modifier.testTag("delete-video-confirm"),
                ) { Text(stringResource(R.string.videos_delete)) }
            },
            dismissButton = {
                TextButton(onClick = { confirmingDelete = null }) { Text(stringResource(R.string.cancel)) }
            },
        )
    }

    state.deleteError?.let { error ->
        AlertDialog(
            onDismissRequest = onDismissDeleteError,
            title = { Text(stringResource(R.string.videos_delete_failed)) },
            text = { Text(error.message()) },
            confirmButton = { TextButton(onClick = onDismissDeleteError) { Text(stringResource(R.string.ok)) } },
        )
    }
}

@Composable
private fun UploadDialog(
    name: String,
    state: VideosUiState,
    onNameChange: (String) -> Unit,
    onUpload: (String) -> Unit,
    onCancel: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = { if (!state.isUploading) onCancel() },
        title = { Text(stringResource(R.string.videos_name_title)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                FormField(
                    value = name,
                    onValueChange = onNameChange,
                    label = stringResource(R.string.videos_name),
                    modifier = Modifier.fillMaxWidth().testTag("video-name"),
                )
                if (state.isUploading) {
                    LinearProgressIndicator(
                        progress = { state.uploadProgress },
                        modifier = Modifier.fillMaxWidth().testTag("upload-progress"),
                    )
                    Text(
                        stringResource(R.string.videos_uploading, (state.uploadProgress * 100).roundToInt()),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                state.uploadError?.let { error ->
                    Text(
                        "${stringResource(R.string.videos_upload_failed)} ${error.message()}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.error,
                        modifier = Modifier.testTag("upload-error"),
                    )
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onUpload(name) },
                enabled = name.isNotBlank() && !state.isUploading,
                modifier = Modifier.testTag("upload-confirm"),
            ) { Text(stringResource(R.string.videos_upload)) }
        },
        dismissButton = {
            TextButton(onClick = onCancel, enabled = !state.isUploading) { Text(stringResource(R.string.cancel)) }
        },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VideoList(
    state: VideosUiState,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onDelete: (Video) -> Unit,
    onDownload: (DownloadLink) -> Unit,
) {
    val error = state.error

    when {
        state.isLoading && !state.hasLoaded -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        error != null && !state.hasLoaded -> StateMessage(
                icon = R.drawable.ic_info,
            title = stringResource(R.string.videos_error_title),
            body = error.message(),
            actionLabel = stringResource(R.string.retry),
            onAction = onRetry,
            isError = true,
            modifier = Modifier.testTag("videos-error"),
        )
        else -> {
            val listState = rememberLazyListState()
            val zone = remember { ZoneId.systemDefault() }

            LoadMoreWhenNearEnd(listState, state.hasMore, state.page, state.loadMoreError != null, onLoadMore)

            PullToRefreshBox(isRefreshing = state.isRefreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
                LazyColumn(state = listState, modifier = Modifier.fillMaxSize().testTag("videos-list")) {
                    if (state.videos.isEmpty()) {
                        item {
                            StateMessage(
                icon = R.drawable.ic_info,
                                title = stringResource(R.string.videos_empty_title),
                                body = stringResource(R.string.videos_empty_body),
                                modifier = Modifier.testTag("videos-empty"),
                            )
                        }
                    }
                    items(state.videos, key = { it.id }) { video ->
                        VideoRow(
                            video = video,
                            zone = zone,
                            isDeleting = state.deletingId == video.id,
                            onDelete = { onDelete(video) },
                            onDownload = onDownload,
                        )
                        HorizontalDivider(color = MaterialTheme.colorScheme.outline)
                    }
                    if (state.isLoadingMore) {
                        item {
                            Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(modifier = Modifier.size(24.dp))
                            }
                        }
                    }
                    state.loadMoreError?.let { loadMoreError ->
                        item {
                            Column(modifier = Modifier.fillMaxWidth().padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(loadMoreError.message(), color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                                OutlinedButton(onClick = onLoadMore) { Text(stringResource(R.string.retry)) }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun VideoRow(video: Video, zone: ZoneId, isDeleting: Boolean, onDelete: () -> Unit, onDownload: (DownloadLink) -> Unit) {
    val context = LocalContext.current
    val details = listOfNotNull(
        video.size?.let { Formatter.formatShortFileSize(context, it) },
        video.durationSeconds?.let(::formatDuration),
        video.createdAt?.atZone(zone)?.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM)),
    ).joinToString(" · ")

    Row(
        modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 8.dp, top = 12.dp, bottom = 12.dp).testTag("video-${video.id}"),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(video.name, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
            if (details.isNotEmpty()) {
                Text(details, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        video.download?.let { link ->
            IconButton(onClick = { onDownload(link) }, modifier = Modifier.testTag("download-video-${video.id}")) {
                Icon(painterResource(R.drawable.ic_download), contentDescription = stringResource(R.string.download))
            }
        }
        if (isDeleting) {
            CircularProgressIndicator(modifier = Modifier.padding(12.dp).size(20.dp), strokeWidth = 2.dp)
        } else {
            TextButton(onClick = onDelete, modifier = Modifier.testTag("delete-video-${video.id}")) {
                Text(stringResource(R.string.videos_delete), color = MaterialTheme.colorScheme.error)
            }
        }
    }
}

/** 75.5 → "1:16", 3725 → "1:02:05". */
private fun formatDuration(seconds: Double): String {
    val total = seconds.roundToLong()
    val hours = total / 3600
    val minutes = total % 3600 / 60
    val rest = total % 60

    return if (hours > 0) "%d:%02d:%02d".format(hours, minutes, rest) else "%d:%02d".format(minutes, rest)
}
