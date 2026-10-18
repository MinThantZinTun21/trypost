package com.minthantzintun.trypost.feature.posts

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.PrimaryTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.ui.PostStatusPill
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.model.toPlainText
import com.minthantzintun.trypost.core.ui.LoadMoreWhenNearEnd
import com.minthantzintun.trypost.core.ui.label
import com.minthantzintun.trypost.core.ui.message
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

@Composable
fun PostsRoute(
    ownerName: String,
    onOpenPost: (String) -> Unit,
    onOpenSettings: () -> Unit,
    viewModel: PostsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    PostsScreen(
        state = state,
        ownerName = ownerName,
        onSelectTab = viewModel::selectTab,
        onOpenAccounts = viewModel::loadAccounts,
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
    ownerName: String,
    onSelectTab: (PostTab) -> Unit,
    onOpenAccounts: () -> Unit,
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
                    title = {
                        Column {
                            Text(stringResource(R.string.posts_title), style = MaterialTheme.typography.titleLarge)
                            Text(ownerName, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    },
                    actions = {
                        IconButton(onClick = onOpenSettings, modifier = Modifier.testTag("settings")) {
                            Icon(painterResource(R.drawable.ic_settings), contentDescription = stringResource(R.string.settings_title))
                        }
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
                AccountFilter(state = state, onOpen = onOpenAccounts, onSelect = onSelectAccount)
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
        tabState.isLoading && tabState.posts.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        error != null && tabState.posts.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            StateMessage(
                icon = R.drawable.ic_info,
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

            LoadMoreWhenNearEnd(listState, tabState.hasMore, tabState.page, tabState.loadMoreError != null, onLoadMore)

            PullToRefreshBox(
                isRefreshing = tabState.isRefreshing,
                onRefresh = onRefresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                LazyColumn(state = listState, modifier = Modifier.fillMaxSize().testTag("posts-list")) {
                    if (sections.isEmpty()) {
                        item {
                            StateMessage(
                icon = R.drawable.ic_info,
                                title = stringResource(tab.emptyTitleRes()),
                                body = stringResource(tab.emptyBodyRes()),
                                modifier = Modifier.testTag("posts-empty"),
                            )
                        }
                    }
                    sections.forEach { section ->
                        stickyHeader(key = "day-${section.day}") { DayHeader(section.day) }
                        items(section.posts, key = { it.id }) { post ->
                            PostRow(post = post, tab = tab, zone = zone, onClick = { onOpenPost(post.id) })
                            HorizontalDivider(color = MaterialTheme.colorScheme.outline)
                        }
                    }
                    if (tabState.isLoadingMore) {
                        item {
                            Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(modifier = Modifier.size(24.dp))
                            }
                        }
                    }
                    tabState.loadMoreError?.let { loadMoreError ->
                        item {
                            Column(
                                modifier = Modifier.fillMaxWidth().padding(16.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                            ) {
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
private fun DayHeader(day: LocalDate?) {
    val today = LocalDate.now()
    val label = when (day) {
        null -> stringResource(R.string.posts_no_date)
        today -> stringResource(R.string.posts_today)
        today.plusDays(1) -> stringResource(R.string.posts_tomorrow)
        today.minusDays(1) -> stringResource(R.string.posts_yesterday)
        else -> day.format(
            if (day.year == today.year) DateTimeFormatter.ofPattern("EEE, d MMM") else DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM),
        )
    }

    Text(
        text = label,
        style = MaterialTheme.typography.labelMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surfaceContainerHigh)
            .padding(horizontal = 16.dp, vertical = 8.dp),
    )
}

@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
private fun PostRow(post: Post, tab: PostTab, zone: ZoneId, onClick: () -> Unit) {
    val time = post.listedAt(tab)?.atZone(zone)?.toLocalTime()?.format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT))
    val preview = post.content.toPlainText().ifBlank { stringResource(R.string.posts_no_text) }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp)
            .testTag("post-${post.id}"),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            text = time ?: "—",
            style = MaterialTheme.typography.labelLarge,
            modifier = Modifier.width(56.dp),
        )
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(text = preview, style = MaterialTheme.typography.bodyMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
                itemVerticalAlignment = Alignment.CenterVertically,
            ) {
                PostStatusPill(post.status)
                post.platforms.forEach { platform ->
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically) {
                        PlatformLogo(platform.platform)
                        Text(
                            text = platform.accountName,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
                if (post.media.isNotEmpty()) {
                    Text(
                        text = pluralStringResource(R.plurals.posts_media_count, post.media.size, post.media.size),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
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
