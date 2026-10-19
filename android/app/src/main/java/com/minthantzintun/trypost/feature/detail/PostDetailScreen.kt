package com.minthantzintun.trypost.feature.detail

import android.content.ClipData
import android.os.Build
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.ClipEntry
import androidx.compose.ui.platform.LocalClipboard
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import coil3.compose.AsyncImage
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ErrorMessage
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Panel
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.designsystem.SectionHeader
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
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
import com.minthantzintun.trypost.core.ui.PlatformStatusPill
import com.minthantzintun.trypost.core.ui.PostStatusPill
import com.minthantzintun.trypost.core.ui.contentTypeLabel
import com.minthantzintun.trypost.core.ui.label
import com.minthantzintun.trypost.core.ui.message
import com.minthantzintun.trypost.core.ui.rememberDownloadAction
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

private val MediaSize = 112.dp

@Composable
fun PostDetailRoute(
    onBack: () -> Unit,
    onEdit: (String) -> Unit,
    viewModel: PostDetailViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    PostDetailScreen(
        state = state,
        onBack = onBack,
        onEdit = onEdit,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onDownload = viewModel::download,
        onDownloadOutcomeShown = viewModel::dismissDownloadOutcome,
        onReschedule = viewModel::reschedule,
        onPublishNow = viewModel::publishNow,
        onSendToTelegram = viewModel::sendToTelegram,
        onTelegramResultShown = viewModel::dismissTelegramNotice,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostDetailScreen(
    state: PostDetailUiState,
    onBack: () -> Unit,
    onEdit: (String) -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onDownload: (DownloadLink) -> Unit,
    onDownloadOutcomeShown: () -> Unit,
    onReschedule: (Instant) -> Unit,
    onPublishNow: () -> Unit,
    onSendToTelegram: () -> Unit,
    onTelegramResultShown: () -> Unit,
) {
    val snackbarHostState = remember { SnackbarHostState() }
    val download = rememberDownloadAction(snackbarHostState, onDownload)
    val copy = rememberCopyAction(snackbarHostState)
    val post = state.post

    DownloadOutcomeSnackbar(state.downloadOutcome, snackbarHostState, onDownloadOutcomeShown)
    TelegramResultSnackbar(state.telegramNotice, state.telegramError, snackbarHostState, onTelegramResultShown)

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
                actions = {
                    if (post != null) {
                        TelegramButton(post = post, isSending = state.isSendingToTelegram, onSend = onSendToTelegram)
                        if (post.isEditable) {
                            ActionIconButton(
                                icon = R.drawable.ic_edit,
                                label = stringResource(R.string.edit_texts),
                                onClick = { onEdit(post.id) },
                                modifier = Modifier.testTag("edit"),
                            )
                        }
                    }
                },
            )
        },
        bottomBar = {
            if (post != null) {
                ScheduleBar(
                    post = post,
                    isSaving = state.isSaving,
                    actionError = state.actionError,
                    onReschedule = onReschedule,
                    onPublishNow = onPublishNow,
                )
            }
        },
    ) { padding ->
        val error = state.error

        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                post == null && state.isLoading -> DetailSkeleton()
                post == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = R.drawable.ic_cloud_off,
                        title = stringResource(R.string.detail_error_title),
                        body = error?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = onRetry,
                        isError = true,
                    )
                }
                else -> PullToRefreshBox(isRefreshing = state.isRefreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
                    PostDetailContent(
                        post = post,
                        error = error?.message(),
                        onDownload = download,
                        onCopy = copy,
                    )
                }
            }
        }
    }
}

@Composable
private fun PostDetailContent(
    post: Post,
    error: String?,
    onDownload: (DownloadLink) -> Unit,
    onCopy: (label: String, text: String) -> Unit,
) {
    val zone = remember { ZoneId.systemDefault() }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = Spacing.gutter, vertical = Spacing.sm)
            .testTag("post-detail"),
        verticalArrangement = Arrangement.spacedBy(Spacing.lg),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            PostStatusPill(post.status, modifier = Modifier.testTag("post-status"))
            Text(text = post.whenLine(zone), style = MaterialTheme.typography.headlineSmall)
            if (post.status == PostStatus.Publishing) {
                Text(
                    text = stringResource(R.string.detail_publishing_note),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (error != null) {
                Text(text = error, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }

        val content = post.content.toPlainText()
        val contentLabel = stringResource(R.string.detail_content)
        Column {
            SectionHeader(
                title = contentLabel,
                trailing = {
                    if (content.isNotBlank()) {
                        CopyButton(contentLabel, "copy-content") { onCopy(contentLabel, content) }
                    }
                },
            )
            Panel {
                SelectionContainer {
                    Text(
                        text = content.ifBlank { stringResource(R.string.posts_no_text) },
                        style = MaterialTheme.typography.bodyLarge,
                        modifier = Modifier.padding(Spacing.lg).testTag("post-content"),
                    )
                }
            }
        }

        if (post.media.isNotEmpty()) {
            Column {
                SectionHeader(stringResource(R.string.detail_media))
                Row(
                    modifier = Modifier.horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                ) {
                    post.media.forEach { MediaThumbnail(it, onDownload) }
                }
            }
        }

        Column {
            SectionHeader(stringResource(R.string.detail_accounts))
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
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
private fun MediaThumbnail(item: MediaItem, onDownload: (DownloadLink) -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(MediaSize)) {
        MediaPreview(item)
        // Photos and videos; the platforms here publish no PDFs.
        item.download?.takeIf { item.type != MediaType.Document }?.let { link ->
            TextButton(
                onClick = { onDownload(link) },
                contentPadding = ButtonDefaults.TextButtonWithIconContentPadding,
                modifier = Modifier.testTag("download-media-${item.id}"),
            ) {
                Icon(painterResource(R.drawable.ic_download), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                Text(stringResource(R.string.download), modifier = Modifier.padding(start = Spacing.xs))
            }
        }
    }
}

@Composable
private fun MediaPreview(item: MediaItem) {
    val modifier = Modifier
        .size(MediaSize)
        .clip(MaterialTheme.shapes.medium)
        .background(MaterialTheme.colorScheme.surfaceContainerHighest)

    when (item.type) {
        MediaType.Image -> AsyncImage(
            model = item.url,
            contentDescription = item.originalFilename,
            contentScale = ContentScale.Crop,
            modifier = modifier,
        )
        // There are no video thumbnails on the server, so videos and PDFs show their kind and name.
        MediaType.Video, MediaType.Document -> Column(
            modifier = modifier.padding(Spacing.sm),
            verticalArrangement = Arrangement.spacedBy(Spacing.xs, Alignment.CenterVertically),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(
                painter = painterResource(if (item.type == MediaType.Video) R.drawable.ic_play else R.drawable.ic_info),
                contentDescription = stringResource(if (item.type == MediaType.Video) R.string.detail_video else R.string.detail_document),
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(IconSize.md),
            )
            item.originalFilename?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
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

    Panel(modifier = Modifier.testTag("platform-${platform.id}")) {
        Column(modifier = Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.md), verticalAlignment = Alignment.CenterVertically) {
                PlatformLogo(platform.platform, size = IconSize.md)
                Column(Modifier.weight(1f)) {
                    Text(platform.accountName, style = MaterialTheme.typography.titleMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    val subtitle = listOfNotNull(platform.accountUsername?.let { "@$it" }, contentTypeLabel(platform.contentType))
                    if (subtitle.isNotEmpty()) {
                        Text(
                            text = subtitle.joinToString(" · "),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                PlatformStatusPill(platform.status)
            }

            platform.shownTexts().forEach { (text, value) ->
                val label = text.label()
                Row(verticalAlignment = Alignment.Top) {
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
                        Text(
                            text = label,
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Text(text = value, style = MaterialTheme.typography.bodyMedium)
                    }
                    CopyButton(label, "copy-${platform.id}-${text.metaKey}") { onCopy("${platform.accountName} $label", value) }
                }
            }

            platform.errorMessage?.takeIf { platform.status == PlatformStatus.Failed || platform.status == PlatformStatus.Retrying }?.let {
                ErrorMessage(lines = listOf(it), modifier = Modifier.testTag("platform-error-${platform.id}"))
            }

            platform.publishedAt?.let {
                Text(
                    text = stringResource(R.string.detail_published_at, it.formatted(zone)),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            platform.platformUrl?.let { url ->
                TextButton(
                    onClick = { uriHandler.openUri(url) },
                    contentPadding = ButtonDefaults.TextButtonWithIconContentPadding,
                    modifier = Modifier.testTag("platform-link-${platform.id}"),
                ) {
                    Icon(painterResource(R.drawable.ic_external), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    Text(stringResource(R.string.detail_view_on, platform.platform.label), modifier = Modifier.padding(start = Spacing.xs))
                }
            }
        }
    }
}

/** Holds the page's shape while the Post loads. */
@Composable
private fun DetailSkeleton() {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = Spacing.gutter, vertical = Spacing.sm),
        verticalArrangement = Arrangement.spacedBy(Spacing.md),
    ) {
        Skeleton(Modifier.width(96.dp).height(24.dp), shape = MaterialTheme.shapes.extraLarge)
        Skeleton(Modifier.fillMaxWidth(0.7f).height(28.dp))
        Skeleton(Modifier.fillMaxWidth().height(120.dp), shape = MaterialTheme.shapes.large)
        Skeleton(Modifier.size(MediaSize), shape = MaterialTheme.shapes.medium)
        Skeleton(Modifier.fillMaxWidth().height(96.dp), shape = MaterialTheme.shapes.large)
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
