package com.minthantzintun.trypost.feature.posts

import androidx.annotation.DrawableRes
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.PrimaryTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import coil3.compose.AsyncImage
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.model.toPlainText
import com.minthantzintun.trypost.core.ui.LoadMoreWhenNearEnd
import com.minthantzintun.trypost.core.ui.PostStatusPill
import com.minthantzintun.trypost.core.ui.message
import kotlinx.coroutines.delay
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

private val ThumbnailSize = 64.dp

@Composable
fun PostsRoute(
    onOpenPost: (String) -> Unit,
    onOpenSettings: () -> Unit,
    viewModel: PostsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    // Each visit lists the accounts again, so a newly connected one shows.
    LaunchedEffect(Unit) { viewModel.loadAccounts() }

    PostsScreen(
        state = state,
        onSelectTab = viewModel::selectTab,
        onRetryAccounts = viewModel::loadAccounts,
        onSelectAccount = viewModel::selectAccount,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onLoadMore = viewModel::loadMore,
        onOpenPost = onOpenPost,
        onOpenSettings = onOpenSettings,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostsScreen(
    state: PostsUiState,
    onSelectTab: (PostTab) -> Unit,
    onRetryAccounts: () -> Unit,
    onSelectAccount: (String?) -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenPost: (String) -> Unit,
    onOpenSettings: () -> Unit,
) {
    Scaffold(
        topBar = {
            Column {
                TopAppBar(
                    title = { Text(stringResource(R.string.posts_title)) },
                    actions = {
                        ActionIconButton(
                            icon = R.drawable.ic_settings,
                            label = stringResource(R.string.settings_title),
                            onClick = onOpenSettings,
                            modifier = Modifier.testTag("settings"),
                        )
                    },
                )
                PrimaryTabRow(selectedTabIndex = state.selectedTab.ordinal) {
                    PostTab.entries.forEach { tab ->
                        Tab(
                            selected = tab == state.selectedTab,
                            onClick = { onSelectTab(tab) },
                            text = { Text(stringResource(tab.titleRes()), maxLines = 1) },
                            modifier = Modifier.testTag("tab-${tab.wire}"),
                        )
                    }
                }
                AccountFilter(state = state, onRetry = onRetryAccounts, onSelect = onSelectAccount)
            }
        },
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            TabContent(
                tab = state.selectedTab,
                tabState = state.current,
                onRefresh = onRefresh,
                onRetry = onRetry,
                onLoadMore = onLoadMore,
                onOpenPost = onOpenPost,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalFoundationApi::class)
@Composable
private fun TabContent(
    tab: PostTab,
    tabState: TabState,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenPost: (String) -> Unit,
) {
    val error = tabState.error

    when {
        tabState.isLoading && tabState.posts.isEmpty() -> PostsSkeleton()
        error != null && tabState.posts.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            StateMessage(
                icon = R.drawable.ic_cloud_off,
                title = stringResource(R.string.posts_error_title),
                body = error.message(),
                actionLabel = stringResource(R.string.retry),
                onAction = onRetry,
                isError = true,
                modifier = Modifier.testTag("posts-error"),
            )
        }
        else -> {
            val zone = remember { ZoneId.systemDefault() }
            val sections = remember(tabState.posts, tab, zone) { groupByDay(tabState.posts, tab, zone) }
            val listState = remember(tab) { LazyListState() }
            val lifecycle = LocalLifecycleOwner.current.lifecycle
            val now by produceState(Instant.now(), lifecycle) {
                lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
                    while (true) {
                        value = Instant.now()
                        delay(30_000)
                    }
                }
            }

            LoadMoreWhenNearEnd(listState, tabState.hasMore, tabState.page, tabState.loadMoreError != null, onLoadMore)

            PullToRefreshBox(
                isRefreshing = tabState.isRefreshing,
                onRefresh = onRefresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                LazyColumn(
                    state = listState,
                    contentPadding = PaddingValues(start = Spacing.gutter, end = Spacing.gutter, bottom = Spacing.xl),
                    verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    modifier = Modifier.fillMaxSize().testTag("posts-list"),
                ) {
                    if (sections.isEmpty()) {
                        item {
                            StateMessage(
                                icon = tab.emptyIcon(),
                                title = stringResource(tab.emptyTitleRes()),
                                body = stringResource(tab.emptyBodyRes()),
                                modifier = Modifier.padding(top = 48.dp).testTag("posts-empty"),
                            )
                        }
                    }
                    sections.forEach { section ->
                        stickyHeader(key = "day-${section.day}") { DayHeader(section.day) }
                        items(section.posts, key = { it.id }) { post ->
                            PostCard(post = post, tab = tab, zone = zone, now = now, onClick = { onOpenPost(post.id) })
                        }
                    }
                    if (tabState.isLoadingMore) {
                        item {
                            Box(Modifier.fillMaxWidth().padding(Spacing.lg), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(modifier = Modifier.size(IconSize.md))
                            }
                        }
                    }
                    tabState.loadMoreError?.let { loadMoreError ->
                        item {
                            Column(
                                modifier = Modifier.fillMaxWidth().padding(Spacing.lg),
                                horizontalAlignment = Alignment.CenterHorizontally,
                            ) {
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
private fun DayHeader(day: LocalDate?) {
    val today = LocalDate.now()
    val label = when (day) {
        null -> stringResource(R.string.posts_no_date)
        today -> stringResource(R.string.posts_today)
        today.plusDays(1) -> stringResource(R.string.posts_tomorrow)
        today.minusDays(1) -> stringResource(R.string.posts_yesterday)
        else -> day.format(
            if (day.year == today.year) DateTimeFormatter.ofPattern("EEEE, d MMM") else DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM),
        )
    }

    Text(
        text = label,
        style = MaterialTheme.typography.titleSmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surface)
            .padding(top = Spacing.md, bottom = Spacing.xs, start = Spacing.xs)
            .semantics { heading() },
    )
}

@Composable
private fun PostCard(post: Post, tab: PostTab, zone: ZoneId, now: Instant, onClick: () -> Unit) {
    val listedAt = post.listedAt(tab)
    val time = listedAt?.atZone(zone)?.toLocalTime()?.format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT))
    val until = listedAt?.takeIf { post.status == PostStatus.Scheduled }?.let { timeUntil(it, now) }
    val preview = post.content.toPlainText().ifBlank { stringResource(R.string.posts_no_text) }

    Card(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
        modifier = Modifier.fillMaxWidth().testTag("post-${post.id}"),
    ) {
        Row(
            modifier = Modifier.padding(Spacing.md),
            horizontalArrangement = Arrangement.spacedBy(Spacing.md),
        ) {
            Thumbnail(post.thumbnail())
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Text(text = time ?: "—", style = MaterialTheme.typography.titleSmall)
                    until?.let {
                        Text(
                            text = it.label(),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.primary,
                            maxLines = 1,
                            modifier = Modifier.weight(1f, fill = false),
                        )
                    }
                    Spacer(Modifier.weight(1f))
                    PostStatusPill(post.status)
                }
                Text(
                    text = preview,
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Accounts(post)
            }
        }
    }
}

@Composable
private fun Thumbnail(thumbnail: PostThumbnail) {
    val modifier = Modifier
        .size(ThumbnailSize)
        .clip(MaterialTheme.shapes.medium)
        .background(MaterialTheme.colorScheme.surfaceContainerHighest)

    when (thumbnail) {
        is PostThumbnail.Image -> AsyncImage(
            model = thumbnail.url,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = modifier,
        )
        PostThumbnail.Video -> Box(modifier = modifier, contentAlignment = Alignment.Center) {
            Icon(
                painter = painterResource(R.drawable.ic_play),
                contentDescription = stringResource(R.string.posts_video_thumbnail),
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(IconSize.md),
            )
        }
        PostThumbnail.None -> Unit
    }
}

/** Each account's logo, with the first account's name; the rest count as +N. */
@Composable
private fun Accounts(post: Post) {
    val first = post.platforms.firstOrNull() ?: return

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.xs)) {
        post.platforms.distinctBy { it.platform }.forEach { PlatformLogo(it.platform, size = IconSize.sm) }
        Spacer(Modifier.width(Spacing.xxs))
        Text(
            text = first.accountName,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        if (post.platforms.size > 1) {
            Text(
                text = stringResource(R.string.posts_more_accounts, post.platforms.size - 1),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun TimeUntil.label(): String = when (this) {
    is TimeUntil.Minutes -> stringResource(R.string.posts_in_minutes, minutes)
    is TimeUntil.Hours -> if (minutes == 0L) {
        stringResource(R.string.posts_in_hours, hours)
    } else {
        stringResource(R.string.posts_in_hours_minutes, hours, minutes)
    }
    is TimeUntil.Days -> stringResource(R.string.posts_in_days, days)
}

/** Holds the cards' shape while the first page loads, so nothing jumps when it arrives. */
@Composable
private fun PostsSkeleton() {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = Spacing.gutter, vertical = Spacing.md).testTag("posts-loading"),
        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
    ) {
        Skeleton(Modifier.width(96.dp).height(16.dp))
        repeat(5) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(MaterialTheme.colorScheme.surfaceContainerLow, MaterialTheme.shapes.large)
                    .padding(Spacing.md),
                horizontalArrangement = Arrangement.spacedBy(Spacing.md),
            ) {
                Skeleton(Modifier.size(ThumbnailSize), shape = MaterialTheme.shapes.medium)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Skeleton(Modifier.width(120.dp).height(14.dp))
                    Skeleton(Modifier.fillMaxWidth().height(12.dp))
                    Skeleton(Modifier.fillMaxWidth(0.6f).height(12.dp))
                }
            }
        }
    }
}

private fun PostTab.titleRes(): Int = when (this) {
    PostTab.Scheduled -> R.string.tab_scheduled
    PostTab.Published -> R.string.tab_published
    PostTab.Failed -> R.string.tab_failed
}

@DrawableRes
private fun PostTab.emptyIcon(): Int = when (this) {
    PostTab.Scheduled -> R.drawable.ic_calendar
    PostTab.Published -> R.drawable.ic_send
    PostTab.Failed -> R.drawable.ic_check
}

private fun PostTab.emptyTitleRes(): Int = when (this) {
    PostTab.Scheduled -> R.string.empty_scheduled_title
    PostTab.Published -> R.string.empty_published_title
    PostTab.Failed -> R.string.empty_failed_title
}

private fun PostTab.emptyBodyRes(): Int = when (this) {
    PostTab.Scheduled -> R.string.empty_scheduled_body
    PostTab.Published -> R.string.empty_published_body
    PostTab.Failed -> R.string.empty_failed_body
}
