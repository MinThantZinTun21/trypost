package com.minthantzintun.trypost.feature.detail

import android.content.ClipData
import android.os.Build
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.ClipEntry
import androidx.compose.ui.platform.LocalClipboard
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import coil3.compose.AsyncImage
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.MessageState
import com.minthantzintun.trypost.core.designsystem.PlatformTag
import com.minthantzintun.trypost.core.designsystem.StatusBadge
import com.minthantzintun.trypost.core.designsystem.asLabel
import com.minthantzintun.trypost.core.model.DownloadLink
import com.minthantzintun.trypost.core.model.MediaItem
import com.minthantzintun.trypost.core.model.MediaType
import com.minthantzintun.trypost.core.model.PlatformStatus
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostPlatform
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.model.shownTexts
import com.minthantzintun.trypost.core.model.toPlainText
import com.minthantzintun.trypost.core.ui.DownloadOutcomeSnackbar
import com.minthantzintun.trypost.core.ui.color
import com.minthantzintun.trypost.core.ui.contentTypeLabel
import com.minthantzintun.trypost.core.ui.label
import com.minthantzintun.trypost.core.ui.message
import com.minthantzintun.trypost.core.ui.rememberDownloadAction
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

@Composable
fun PostDetailRoute(
    onBack: () -> Unit,
    extraActions: @Composable (Post) -> Unit = {},
    viewModel: PostDetailViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    PostDetailScreen(
        state = state,
        onBack = onBack,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onDownload = viewModel::download,
        onDownloadOutcomeShown = viewModel::dismissDownloadOutcome,
        actions = { post ->
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                ScheduleActions(
                    post = post,
                    isSaving = state.isSaving,
                    actionError = state.actionError,
                    onReschedule = viewModel::reschedule,
                    onPublishNow = viewModel::publishNow,
                    extraActions = { extraActions(post) },
                )
                TelegramAction(
                    post = post,
                    isSending = state.isSendingToTelegram,
                    notice = state.telegramNotice,
                    error = state.telegramError,
                    onSend = viewModel::sendToTelegram,
                )
            }
        },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostDetailScreen(
    state: PostDetailUiState,
    onBack: () -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onDownload: (DownloadLink) -> Unit = {},
    onDownloadOutcomeShown: () -> Unit = {},
    actions: @Composable (Post) -> Unit = {},
) {
    val snackbarHostState = remember { SnackbarHostState() }
    val download = rememberDownloadAction(snackbarHostState, onDownload)
    val copy = rememberCopyAction(snackbarHostState)

    DownloadOutcomeSnackbar(state.downloadOutcome, snackbarHostState, onDownloadOutcomeShown)

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.detail_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
            )
        },
    ) { padding ->
        val post = state.post
        val error = state.error

        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                post == null && state.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
                post == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    MessageState(
                        title = stringResource(R.string.detail_error_title),
                        body = error?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = onRetry,
                        isError = true,
                    )
                }
                else -> PullToRefreshBox(isRefreshing = state.isRefreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
                    PostDetailContent(post = post, error = error?.message(), actions = actions, onDownload = download, onCopy = copy)
                }
            }
        }
    }
}

@Composable
private fun PostDetailContent(
    post: Post,
    error: String?,
    actions: @Composable (Post) -> Unit,
    onDownload: (DownloadLink) -> Unit,
    onCopy: (label: String, text: String) -> Unit,
) {
    val zone = remember { ZoneId.systemDefault() }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
            .testTag("post-detail"),
        verticalArrangement = Arrangement.spacedBy(20.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            StatusBadge(label = post.status.label(), color = post.status.color(), modifier = Modifier.testTag("post-status"))
            Text(text = post.whenLine(zone), style = MaterialTheme.typography.titleLarge)
            if (post.status == PostStatus.Publishing) {
                Text(
                    text = stringResource(R.string.detail_publishing_note),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (error != null) {
                Text(text = error, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }

        actions(post)

        val content = post.content.toPlainText()
        val contentLabel = stringResource(R.string.detail_content)
        Section(
            title = contentLabel,
            trailing = content.takeIf { it.isNotBlank() }?.let { { CopyButton(contentLabel, "copy-content") { onCopy(contentLabel, it) } } },
        ) {
            SelectionContainer {
                Text(
                    text = content.ifBlank { stringResource(R.string.posts_no_text) },
                    style = MaterialTheme.typography.bodyLarge,
                    modifier = Modifier.testTag("post-content"),
                )
            }
        }

        if (post.media.isNotEmpty()) {
            Section(title = stringResource(R.string.detail_media)) {
                Row(
                    modifier = Modifier.horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    post.media.forEach { MediaThumbnail(it, onDownload) }
                }
            }
        }

        Section(title = stringResource(R.string.detail_accounts)) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                post.platforms.forEach { PlatformCard(it, zone, onCopy) }
            }
        }
    }
}

@Composable
private fun Post.whenLine(zone: ZoneId): String {
    val publishedAt = publishedAt
    val scheduledAt = scheduledAt

    return when {
        publishedAt != null && (status == PostStatus.Published || status == PostStatus.PartiallyPublished) ->
            stringResource(R.string.detail_published_at, publishedAt.formatted(zone))
        scheduledAt != null -> stringResource(R.string.detail_scheduled_for, scheduledAt.formatted(zone))
        else -> stringResource(R.string.detail_not_scheduled)
    }
}

private fun Instant.formatted(zone: ZoneId): String =
    atZone(zone).format(DateTimeFormatter.ofLocalizedDateTime(FormatStyle.MEDIUM, FormatStyle.SHORT))

@Composable
private fun Section(title: String, trailing: (@Composable () -> Unit)? = null, content: @Composable () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = title.asLabel(),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.weight(1f),
            )
            trailing?.invoke()
        }
        content()
    }
}

@Composable
private fun MediaThumbnail(item: MediaItem, onDownload: (DownloadLink) -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        MediaPreview(item)
        // Photos and videos; the platforms here publish no PDFs.
        item.download?.takeIf { item.type != MediaType.Document }?.let { link ->
            TextButton(onClick = { onDownload(link) }, modifier = Modifier.testTag("download-media-${item.id}")) {
                Text(stringResource(R.string.download).asLabel())
            }
        }
    }
}

@Composable
private fun MediaPreview(item: MediaItem) {
    val modifier = Modifier
        .size(96.dp)
        .border(1.dp, MaterialTheme.colorScheme.outline)

    when (item.type) {
        MediaType.Image -> AsyncImage(
            model = item.url,
            contentDescription = item.originalFilename,
            contentScale = ContentScale.Crop,
            modifier = modifier,
        )
        // There are no video thumbnails on the server, so videos and PDFs show their kind and name.
        MediaType.Video, MediaType.Document -> Column(
            modifier = modifier.background(MaterialTheme.colorScheme.surfaceVariant).padding(8.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                text = stringResource(if (item.type == MediaType.Video) R.string.detail_video else R.string.detail_document).asLabel(),
                style = MaterialTheme.typography.labelMedium,
            )
            item.originalFilename?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun PlatformCard(platform: PostPlatform, zone: ZoneId, onCopy: (label: String, text: String) -> Unit) {
    val uriHandler = LocalUriHandler.current

    Surface(
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        shadowElevation = 1.dp,
        modifier = Modifier.fillMaxWidth().testTag("platform-${platform.id}"),
    ) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                PlatformTag(platform.platform)
                Column(Modifier.weight(1f)) {
                    Text(platform.accountName, style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    val subtitle = listOfNotNull(platform.accountUsername?.let { "@$it" }, contentTypeLabel(platform.contentType))
                    if (subtitle.isNotEmpty()) {
                        Text(
                            text = subtitle.joinToString(" · "),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                StatusBadge(label = platform.status.label(), color = platform.status.color())
            }

            platform.shownTexts().forEach { (text, value) ->
                val label = text.label()
                Row(verticalAlignment = Alignment.Top) {
                    Column(Modifier.weight(1f)) {
                        Text(
                            text = label.asLabel(),
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Text(text = value, style = MaterialTheme.typography.bodyMedium)
                    }
                    CopyButton(label, "copy-${platform.id}-${text.metaKey}") { onCopy("${platform.accountName} $label", value) }
                }
            }

            platform.errorMessage?.takeIf { platform.status == PlatformStatus.Failed || platform.status == PlatformStatus.Retrying }?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.testTag("platform-error-${platform.id}"),
                )
            }

            platform.publishedAt?.let {
                Text(
                    text = stringResource(R.string.detail_published_at, it.formatted(zone)),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            platform.platformUrl?.let { url ->
                TextButton(onClick = { uriHandler.openUri(url) }, modifier = Modifier.testTag("platform-link-${platform.id}")) {
                    Text(stringResource(R.string.detail_view_on, platform.platform.label).asLabel())
                }
            }
        }
    }
}

@Composable
private fun CopyButton(what: String, testTag: String, onClick: () -> Unit) {
    IconButton(onClick = onClick, modifier = Modifier.testTag(testTag)) {
        Icon(painterResource(R.drawable.ic_copy), contentDescription = stringResource(R.string.copy_text, what))
    }
}

/**
 * Puts a text on the clipboard under [label]. Android 13 and later confirm it
 * themselves, so only older versions show "Copied".
 */
@Composable
private fun rememberCopyAction(snackbarHostState: SnackbarHostState): (label: String, text: String) -> Unit {
    val clipboard = LocalClipboard.current
    val scope = rememberCoroutineScope()
    val copied = stringResource(R.string.copied)

    return { label, text ->
        scope.launch {
            clipboard.setClipEntry(ClipEntry(ClipData.newPlainText(label, text)))
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
                snackbarHostState.showSnackbar(copied)
            }
        }
    }
}
