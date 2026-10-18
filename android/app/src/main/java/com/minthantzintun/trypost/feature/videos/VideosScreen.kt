package com.minthantzintun.trypost.feature.videos

import android.text.format.Formatter
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.MenuDefaults
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SheetValue
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.data.ContentUriVideoSource
import com.minthantzintun.trypost.core.data.VideoSource
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
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

/** Room under the list for the Upload button, so it never hides the last Video. */
private val FabClearance = 88.dp
private val VideoTileSize = 56.dp

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
        topBar = { TopAppBar(title = { Text(stringResource(R.string.videos_title)) }) },
        floatingActionButton = {
            if (state.pending == null) {
                ExtendedFloatingActionButton(
                    onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly)) },
                    icon = { Icon(painterResource(R.drawable.ic_upload), contentDescription = null) },
                    text = { Text(stringResource(R.string.videos_upload)) },
                    modifier = Modifier.testTag("upload-video"),
                )
            }
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
        UploadSheet(
            name = pending.name,
            state = state,
            onNameChange = onNameChange,
            onUpload = onUpload,
            onCancel = onCancelUpload,
        )
    }

    confirmingDelete?.let { video ->
        ConfirmDialog(
            title = stringResource(R.string.videos_delete_confirm_title),
            body = stringResource(R.string.videos_delete_confirm_body, video.name),
            confirmLabel = stringResource(R.string.videos_delete),
            onConfirm = {
                confirmingDelete = null
                onDelete(video.id)
            },
            onDismiss = { confirmingDelete = null },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_delete,
            isDestructive = true,
            confirmTestTag = "delete-video-confirm",
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

/**
 * Names the picked Video and shows the upload's progress. While the file goes
 * up, nothing closes the sheet: not a swipe, a tap outside or Back.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun UploadSheet(
    name: String,
    state: VideosUiState,
    onNameChange: (String) -> Unit,
    onUpload: (String) -> Unit,
    onCancel: () -> Unit,
) {
    val isUploading by rememberUpdatedState(state.isUploading)
    val sheetState = rememberModalBottomSheetState(
        skipPartiallyExpanded = true,
        confirmValueChange = { it != SheetValue.Hidden || !isUploading },
    )

    ModalBottomSheet(
        onDismissRequest = { if (!isUploading) onCancel() },
        sheetState = sheetState,
        containerColor = MaterialTheme.colorScheme.surfaceContainerLow,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .imePadding()
                .padding(start = Spacing.xl, end = Spacing.xl, bottom = Spacing.xl)
                .testTag("upload-sheet"),
            verticalArrangement = Arrangement.spacedBy(Spacing.lg),
        ) {
            Text(
                text = stringResource(R.string.videos_name_title),
                style = MaterialTheme.typography.titleLarge,
                modifier = Modifier.semantics { heading() },
            )
            FormField(
                value = name,
                onValueChange = onNameChange,
                label = stringResource(R.string.videos_name),
                enabled = !state.isUploading,
                supportingText = stringResource(R.string.videos_upload_hint),
                modifier = Modifier.fillMaxWidth().testTag("video-name"),
            )
            if (state.isUploading) {
                Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
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
            }
            state.uploadError?.let { error ->
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), modifier = Modifier.testTag("upload-error")) {
                    Icon(
                        painter = painterResource(R.drawable.ic_status_failed),
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.error,
                        modifier = Modifier.size(IconSize.sm),
                    )
                    Text(
                        "${stringResource(R.string.videos_upload_failed)} ${error.message()}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.error,
                    )
                }
            }
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm, Alignment.End),
            ) {
                TextButton(onClick = onCancel, enabled = !state.isUploading) { Text(stringResource(R.string.cancel)) }
                Button(
                    onClick = { onUpload(name) },
                    enabled = name.isNotBlank() && !state.isUploading,
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    modifier = Modifier.testTag("upload-confirm"),
                ) {
                    if (state.isUploading) {
                        CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
                    } else {
                        Icon(painterResource(R.drawable.ic_upload), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    }
                    Text(stringResource(R.string.videos_upload), modifier = Modifier.padding(start = Spacing.sm))
                }
            }
        }
    }
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
        state.isLoading && !state.hasLoaded -> VideosSkeleton()
        error != null && !state.hasLoaded -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            StateMessage(
                icon = R.drawable.ic_cloud_off,
                title = stringResource(R.string.videos_error_title),
                body = error.message(),
                actionLabel = stringResource(R.string.retry),
                onAction = onRetry,
                isError = true,
                modifier = Modifier.testTag("videos-error"),
            )
        }
        else -> {
            val listState = rememberLazyListState()
            val zone = remember { ZoneId.systemDefault() }

            LoadMoreWhenNearEnd(listState, state.hasMore, state.page, state.loadMoreError != null, onLoadMore)

            PullToRefreshBox(isRefreshing = state.isRefreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
                LazyColumn(
                    state = listState,
                    contentPadding = PaddingValues(start = Spacing.gutter, end = Spacing.gutter, top = Spacing.xs, bottom = FabClearance),
                    verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    modifier = Modifier.fillMaxSize().testTag("videos-list"),
                ) {
                    if (state.videos.isEmpty()) {
                        item {
                            StateMessage(
                                icon = R.drawable.ic_videos,
                                title = stringResource(R.string.videos_empty_title),
                                body = stringResource(R.string.videos_empty_body),
                                modifier = Modifier.padding(top = 48.dp).testTag("videos-empty"),
                            )
                        }
                    }
                    items(state.videos, key = { it.id }) { video ->
                        VideoCard(
                            video = video,
                            zone = zone,
                            isDeleting = state.deletingId == video.id,
                            onDelete = { onDelete(video) },
                            onDownload = onDownload,
                        )
                    }
                    if (state.isLoadingMore) {
                        item {
                            Box(Modifier.fillMaxWidth().padding(Spacing.lg), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(modifier = Modifier.size(IconSize.md))
                            }
                        }
                    }
                    state.loadMoreError?.let { loadMoreError ->
                        item {
                            Column(modifier = Modifier.fillMaxWidth().padding(Spacing.lg), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(loadMoreError.message(), color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                                TextButton(onClick = onLoadMore) { Text(stringResource(R.string.retry)) }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun VideoCard(video: Video, zone: ZoneId, isDeleting: Boolean, onDelete: () -> Unit, onDownload: (DownloadLink) -> Unit) {
    val context = LocalContext.current
    val details = listOfNotNull(
        video.size?.let { Formatter.formatShortFileSize(context, it) },
        video.durationSeconds?.let(::formatDuration),
        video.createdAt?.atZone(zone)?.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM)),
    ).joinToString(" · ")
    var isMenuOpen by remember { mutableStateOf(false) }

    Card(
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
        modifier = Modifier.fillMaxWidth().testTag("video-${video.id}"),
    ) {
        Row(
            modifier = Modifier.padding(start = Spacing.md, top = Spacing.md, bottom = Spacing.md, end = Spacing.xs),
            horizontalArrangement = Arrangement.spacedBy(Spacing.md),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(VideoTileSize)
                    .background(MaterialTheme.colorScheme.secondaryContainer, MaterialTheme.shapes.medium),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    painter = painterResource(R.drawable.ic_play),
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSecondaryContainer,
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                Text(video.name, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
                if (details.isNotEmpty()) {
                    Text(details, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            if (isDeleting) {
                Box(Modifier.size(IconSize.touchTarget), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(modifier = Modifier.size(IconSize.md), strokeWidth = 2.dp)
                }
            } else {
                Box {
                    IconButton(onClick = { isMenuOpen = true }, modifier = Modifier.testTag("video-menu-${video.id}")) {
                        Icon(
                            painter = painterResource(R.drawable.ic_more),
                            contentDescription = stringResource(R.string.videos_more_options, video.name),
                        )
                    }
                    DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
                        video.download?.let { link ->
                            DropdownMenuItem(
                                text = { Text(stringResource(R.string.download)) },
                                leadingIcon = { Icon(painterResource(R.drawable.ic_download), contentDescription = null) },
                                onClick = {
                                    isMenuOpen = false
                                    onDownload(link)
                                },
                                modifier = Modifier.testTag("download-video-${video.id}"),
                            )
                        }
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.videos_delete)) },
                            leadingIcon = { Icon(painterResource(R.drawable.ic_delete), contentDescription = null) },
                            colors = MenuDefaults.itemColors(
                                textColor = MaterialTheme.colorScheme.error,
                                leadingIconColor = MaterialTheme.colorScheme.error,
                            ),
                            onClick = {
                                isMenuOpen = false
                                onDelete()
                            },
                            modifier = Modifier.testTag("delete-video-${video.id}"),
                        )
                    }
                }
            }
        }
    }
}

/** Holds the cards' shape while the first page loads, so nothing jumps when it arrives. */
@Composable
private fun VideosSkeleton() {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = Spacing.gutter, vertical = Spacing.xs).testTag("videos-loading"),
        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
    ) {
        repeat(6) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(MaterialTheme.colorScheme.surfaceContainerLow, MaterialTheme.shapes.large)
                    .padding(Spacing.md),
                horizontalArrangement = Arrangement.spacedBy(Spacing.md),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Skeleton(Modifier.size(VideoTileSize), shape = MaterialTheme.shapes.medium)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Skeleton(Modifier.fillMaxWidth(0.7f).height(14.dp))
                    Skeleton(Modifier.width(140.dp).height(12.dp))
                }
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
