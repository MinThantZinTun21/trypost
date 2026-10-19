package com.minthantzintun.trypost.feature.tasks

import androidx.annotation.DrawableRes
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.staggeredgrid.LazyStaggeredGridState
import androidx.compose.foundation.lazy.staggeredgrid.LazyVerticalStaggeredGrid
import androidx.compose.foundation.lazy.staggeredgrid.StaggeredGridCells
import androidx.compose.foundation.lazy.staggeredgrid.StaggeredGridItemSpan
import androidx.compose.foundation.lazy.staggeredgrid.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.InputChip
import androidx.compose.material3.InputChipDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.PrimaryTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.ChecklistProgress
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.model.TaskTagRef
import com.minthantzintun.trypost.core.model.taskTagRefs
import com.minthantzintun.trypost.core.settings.TaskLayout
import com.minthantzintun.trypost.core.ui.LoadMoreWhenNearEnd
import com.minthantzintun.trypost.core.ui.message
import kotlinx.coroutines.delay
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

@Composable
fun TasksRoute(
    onOpenTask: (String) -> Unit,
    onNewTask: (isChecklist: Boolean) -> Unit,
    viewModel: TasksViewModel = hiltViewModel(),
    taskTagsViewModel: TaskTagsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val layout by viewModel.layout.collectAsStateWithLifecycle()
    val taskTagsState by taskTagsViewModel.state.collectAsStateWithLifecycle()

    // A Task tag deleted elsewhere can no longer filter the list.
    LaunchedEffect(taskTagsState.tagGroups) {
        taskTagsState.tagGroups?.let { groups ->
            viewModel.keepOnlyKnownTaskTags(groups.flatMap { group -> group.taskTags.map { it.id } }.toSet())
        }
    }

    TasksScreen(
        state = state,
        layout = layout,
        taskTagsState = taskTagsState,
        onToggleLayout = viewModel::toggleLayout,
        onSelectTab = viewModel::selectTab,
        onQueryChange = viewModel::changeQuery,
        onSearch = viewModel::search,
        onFilter = viewModel::filterByTaskTags,
        onRemoveFilter = viewModel::removeTaskTagFilter,
        onClearFilters = viewModel::clearFilters,
        onRetryTaskTags = taskTagsViewModel::load,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onLoadMore = viewModel::loadMore,
        onOpenTask = onOpenTask,
        onNewTask = onNewTask,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksScreen(
    state: TasksUiState,
    layout: TaskLayout,
    taskTagsState: TaskTagsUiState,
    onToggleLayout: () -> Unit,
    onSelectTab: (TaskTab) -> Unit,
    onQueryChange: (String) -> Unit,
    onSearch: () -> Unit,
    onFilter: (List<String>) -> Unit,
    onRemoveFilter: (String) -> Unit,
    onClearFilters: () -> Unit,
    onRetryTaskTags: () -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenTask: (String) -> Unit,
    onNewTask: (isChecklist: Boolean) -> Unit,
) {
    var isFilterOpen by rememberSaveable { mutableStateOf(false) }
    val taskTagRefs = remember(taskTagsState.tagGroups) { taskTagsState.tagGroups.orEmpty().taskTagRefs() }

    Scaffold(
        topBar = {
            Column {
                TopAppBar(
                    title = { Text(stringResource(R.string.tasks_title)) },
                    actions = {
                        IconButton(onClick = onToggleLayout, modifier = Modifier.testTag("tasks-layout")) {
                            if (layout == TaskLayout.Grid) {
                                Icon(painterResource(R.drawable.ic_layout_list), contentDescription = stringResource(R.string.tasks_layout_list))
                            } else {
                                Icon(painterResource(R.drawable.ic_layout_grid), contentDescription = stringResource(R.string.tasks_layout_grid))
                            }
                        }
                        IconButton(onClick = { isFilterOpen = true }, modifier = Modifier.testTag("tasks-filter")) {
                            BadgedBox(
                                badge = {
                                    if (state.taskTagIds.isNotEmpty()) {
                                        Badge { Text(state.taskTagIds.size.toString()) }
                                    }
                                },
                            ) {
                                Icon(painterResource(R.drawable.ic_filter), contentDescription = stringResource(R.string.tasks_filter))
                            }
                        }
                    },
                )
                SearchField(query = state.query, onQueryChange = onQueryChange, onSearch = onSearch)
                ActiveFilters(
                    taskTagIds = state.taskTagIds,
                    taskTagRefs = taskTagRefs,
                    onRemove = onRemoveFilter,
                    onClear = onClearFilters,
                )
                PrimaryTabRow(selectedTabIndex = state.selectedTab.ordinal) {
                    TaskTab.entries.forEach { tab ->
                        Tab(
                            selected = tab == state.selectedTab,
                            onClick = { onSelectTab(tab) },
                            text = { Text(stringResource(tab.titleRes()), maxLines = 1) },
                            modifier = Modifier.testTag("tasks-tab-${tab.wire}"),
                        )
                    }
                }
            }
        },
        bottomBar = { QuickAddBar(onNewTask = onNewTask) },
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            TaskTabContent(
                tab = state.selectedTab,
                tabState = state.current,
                layout = layout,
                isFiltered = state.isFiltered,
                onClearFilters = onClearFilters,
                onRefresh = onRefresh,
                onRetry = onRetry,
                onLoadMore = onLoadMore,
                onOpenTask = onOpenTask,
            )
        }
    }

    if (isFilterOpen) {
        FilterSheet(
            taskTagsState = taskTagsState,
            appliedIds = state.taskTagIds,
            onApply = {
                isFilterOpen = false
                onFilter(it)
            },
            onRetry = onRetryTaskTags,
            onDismiss = { isFilterOpen = false },
        )
    }
}

@Composable
private fun SearchField(query: String, onQueryChange: (String) -> Unit, onSearch: () -> Unit) {
    val focusManager = LocalFocusManager.current

    FormField(
        value = query,
        onValueChange = onQueryChange,
        label = stringResource(R.string.tasks_search),
        leadingIcon = { Icon(painterResource(R.drawable.ic_search), contentDescription = null, modifier = Modifier.size(IconSize.sm)) },
        trailingIcon = if (query.isNotEmpty()) {
            {
                IconButton(onClick = { onQueryChange("") }, modifier = Modifier.testTag("tasks-search-clear")) {
                    Icon(painterResource(R.drawable.ic_close), contentDescription = stringResource(R.string.tasks_search_clear))
                }
            }
        } else {
            null
        },
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
        keyboardActions = KeyboardActions(
            onSearch = {
                focusManager.clearFocus()
                onSearch()
            },
        ),
        modifier = Modifier.padding(horizontal = Spacing.gutter).padding(bottom = Spacing.sm).testTag("tasks-search"),
    )
}

/** The Task tags the list is filtered to, each removable, then Clear all. */
@Composable
private fun ActiveFilters(
    taskTagIds: List<String>,
    taskTagRefs: Map<String, TaskTagRef>,
    onRemove: (String) -> Unit,
    onClear: () -> Unit,
) {
    if (taskTagIds.isEmpty()) {
        return
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = Spacing.gutter)
            .testTag("tasks-active-filters"),
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        taskTagIds.forEach { id ->
            val taskTag = taskTagRefs[id]
            val tone = taskTag?.color?.tone()
            val removeLabel = stringResource(R.string.tasks_filter_remove, taskTag?.name.orEmpty())

            InputChip(
                selected = true,
                onClick = { onRemove(id) },
                label = { Text(taskTag?.name ?: stringResource(R.string.tasks_filter_unknown_tag), maxLines = 1) },
                trailingIcon = {
                    Icon(painterResource(R.drawable.ic_close), contentDescription = removeLabel, modifier = Modifier.size(IconSize.sm))
                },
                colors = if (tone != null) {
                    InputChipDefaults.inputChipColors(
                        selectedContainerColor = tone.container,
                        selectedLabelColor = tone.content,
                        selectedTrailingIconColor = tone.content,
                    )
                } else {
                    InputChipDefaults.inputChipColors()
                },
                modifier = Modifier.testTag("filter-chip-$id"),
            )
        }
        TextButton(onClick = onClear, modifier = Modifier.testTag("tasks-clear-filters")) {
            Text(stringResource(R.string.tasks_filter_clear_all))
        }
    }
}

/** Picks Task tags to filter by; nothing changes until Apply. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FilterSheet(
    taskTagsState: TaskTagsUiState,
    appliedIds: List<String>,
    onApply: (List<String>) -> Unit,
    onRetry: () -> Unit,
    onDismiss: () -> Unit,
) {
    var selectedIds by rememberSaveable { mutableStateOf(appliedIds) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = MaterialTheme.colorScheme.surfaceContainerLow,
    ) {
        Column(modifier = Modifier.fillMaxWidth().padding(bottom = Spacing.lg).testTag("tasks-filter-sheet")) {
            Text(
                text = stringResource(R.string.tasks_filter_title),
                style = MaterialTheme.typography.titleLarge,
                modifier = Modifier.padding(horizontal = Spacing.xl).semantics { heading() },
            )
            Text(
                text = stringResource(R.string.tasks_filter_hint),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = Spacing.xl, vertical = Spacing.xs),
            )
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f, fill = false)
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = Spacing.xl, vertical = Spacing.md),
            ) {
                TagGroupsContent(
                    state = taskTagsState,
                    selectedIds = selectedIds,
                    onToggle = { id -> selectedIds = if (id in selectedIds) selectedIds - id else selectedIds + id },
                    onRetry = onRetry,
                )
            }
            Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = Spacing.xl),
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm, Alignment.End),
            ) {
                TextButton(
                    onClick = { selectedIds = emptyList() },
                    enabled = selectedIds.isNotEmpty(),
                    modifier = Modifier.testTag("tasks-filter-clear"),
                ) { Text(stringResource(R.string.tasks_filter_clear)) }
                Button(onClick = { onApply(selectedIds) }, modifier = Modifier.testTag("tasks-filter-apply")) {
                    Text(stringResource(R.string.tasks_filter_apply))
                }
            }
        }
    }
}

/** "Take a note…" opens a new Task; the checklist button opens one that starts as a checklist. */
@Composable
private fun QuickAddBar(onNewTask: (isChecklist: Boolean) -> Unit) {
    Surface(color = MaterialTheme.colorScheme.surfaceContainer, modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(horizontal = Spacing.sm, vertical = Spacing.xs),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = stringResource(R.string.tasks_quick_add),
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier
                    .weight(1f)
                    .clip(MaterialTheme.shapes.medium)
                    .clickable(role = Role.Button) { onNewTask(false) }
                    .padding(horizontal = Spacing.md, vertical = Spacing.md)
                    .testTag("new-task"),
            )
            IconButton(onClick = { onNewTask(true) }, modifier = Modifier.testTag("new-checklist")) {
                Icon(painterResource(R.drawable.ic_list_check), contentDescription = stringResource(R.string.tasks_new_checklist))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TaskTabContent(
    tab: TaskTab,
    tabState: TaskTabState,
    layout: TaskLayout,
    isFiltered: Boolean,
    onClearFilters: () -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenTask: (String) -> Unit,
) {
    val error = tabState.error

    when {
        tabState.isLoading && tabState.tasks.isEmpty() -> TasksSkeleton(layout)
        error != null && tabState.tasks.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            StateMessage(
                icon = R.drawable.ic_cloud_off,
                title = stringResource(R.string.tasks_error_title),
                body = error.message(),
                actionLabel = stringResource(R.string.retry),
                onAction = onRetry,
                isError = true,
                modifier = Modifier.testTag("tasks-error"),
            )
        }
        else -> {
            val zone = remember { ZoneId.systemDefault() }
            val lifecycle = LocalLifecycleOwner.current.lifecycle
            val now by produceState(Instant.now(), lifecycle) {
                lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
                    while (true) {
                        value = Instant.now()
                        delay(30_000)
                    }
                }
            }
            val today = now.atZone(zone).toLocalDate()
            // Sections move with the clock only when a day turns or a due passes, so the minute is enough.
            val sections = remember(tabState.tasks, tab, zone, now.epochSecond / 60) {
                if (tab == TaskTab.Open) groupTasksIntoSections(tabState.tasks, now, zone) else emptyList()
            }
            val gridState = remember(tab) { LazyStaggeredGridState() }

            LoadMoreWhenNearEnd(gridState, tabState.hasMore, tabState.page, tabState.loadMoreError != null, onLoadMore)

            PullToRefreshBox(
                isRefreshing = tabState.isRefreshing,
                onRefresh = onRefresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                LazyVerticalStaggeredGrid(
                    columns = StaggeredGridCells.Fixed(if (layout == TaskLayout.Grid) 2 else 1),
                    state = gridState,
                    contentPadding = PaddingValues(start = Spacing.gutter, end = Spacing.gutter, top = Spacing.sm, bottom = Spacing.lg),
                    horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                    verticalItemSpacing = Spacing.sm,
                    modifier = Modifier.fillMaxSize().testTag("tasks-list"),
                ) {
                    if (tabState.tasks.isEmpty()) {
                        item(span = StaggeredGridItemSpan.FullLine) {
                            EmptyTasks(tab = tab, isFiltered = isFiltered, onClearFilters = onClearFilters)
                        }
                    }
                    if (tab == TaskTab.Open) {
                        sections.forEach { section ->
                            item(key = "section-${section.kind}", span = StaggeredGridItemSpan.FullLine) { SectionHeader(section.kind) }
                            items(section.tasks, key = { it.id }) { task ->
                                TaskCard(task = task, zone = zone, now = now, today = today, onClick = { onOpenTask(task.id) })
                            }
                        }
                    } else {
                        items(tabState.tasks, key = { it.id }) { task ->
                            TaskCard(task = task, zone = zone, now = now, today = today, onClick = { onOpenTask(task.id) })
                        }
                    }
                    if (tabState.isLoadingMore) {
                        item(span = StaggeredGridItemSpan.FullLine) {
                            Box(Modifier.fillMaxWidth().padding(Spacing.lg), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(modifier = Modifier.size(IconSize.md))
                            }
                        }
                    }
                    tabState.loadMoreError?.let { loadMoreError ->
                        item(span = StaggeredGridItemSpan.FullLine) {
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
private fun SectionHeader(kind: TaskSectionKind) {
    Text(
        text = stringResource(kind.titleRes()),
        style = MaterialTheme.typography.labelLarge,
        color = if (kind == TaskSectionKind.Overdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = Spacing.sm, start = Spacing.xs)
            .semantics { heading() }
            .testTag("tasks-section-${kind.name.lowercase()}"),
    )
}

@Composable
private fun EmptyTasks(tab: TaskTab, isFiltered: Boolean, onClearFilters: () -> Unit) {
    if (isFiltered) {
        StateMessage(
            icon = R.drawable.ic_search,
            title = stringResource(R.string.tasks_empty_filtered_title),
            body = stringResource(R.string.tasks_empty_filtered_body),
            actionLabel = stringResource(R.string.tasks_filter_clear_all),
            onAction = onClearFilters,
            modifier = Modifier.padding(top = 48.dp).testTag("tasks-empty"),
        )
    } else {
        StateMessage(
            icon = tab.emptyIcon(),
            title = stringResource(tab.emptyTitleRes()),
            body = stringResource(tab.emptyBodyRes()),
            modifier = Modifier.padding(top = 48.dp).testTag("tasks-empty"),
        )
    }
}

/**
 * One Task as a Keep card in its colour: the title, a few lines of the body
 * (or a checklist's first rows) and how many more, then the checklist's
 * progress, the due, In progress, the priority and the Task tags.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TaskCard(task: Task, zone: ZoneId, now: Instant, today: LocalDate, onClick: () -> Unit) {
    val isOverdue = task.isOverdue(now, zone)
    val due = task.due(zone)
    val preview = remember(task.description) { taskCardPreview(task.description) }
    val tone = task.color?.tone()

    Card(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = tone?.container ?: MaterialTheme.colorScheme.surface),
        border = if (tone == null) BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant) else null,
        modifier = Modifier.fillMaxWidth().testTag("task-${task.id}"),
    ) {
        Column(modifier = Modifier.padding(Spacing.md), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            if (task.title != null || task.isPinned) {
                Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                    Text(
                        text = task.title.orEmpty(),
                        style = MaterialTheme.typography.titleMedium,
                        maxLines = 3,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                    if (task.isPinned) {
                        Icon(
                            painter = painterResource(R.drawable.ic_pin),
                            contentDescription = stringResource(R.string.task_pinned),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.size(IconSize.sm).testTag("task-${task.id}-pinned"),
                        )
                    }
                }
            }
            if (preview != null) {
                TaskBodyBlocks(blocks = preview.blocks, isCompact = true)
                if (preview.moreCount > 0) {
                    Text(
                        text = pluralStringResource(R.plurals.tasks_card_more, preview.moreCount, preview.moreCount),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.testTag("task-${task.id}-more"),
                    )
                }
            }
            val checklist = preview?.checklist
            if (checklist != null || due != null || task.priority != TaskPriority.None || task.status == TaskStatus.InProgress) {
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                    verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                    itemVerticalAlignment = Alignment.CenterVertically,
                ) {
                    if (checklist != null) {
                        ChecklistProgressText(checklist, modifier = Modifier.testTag("task-${task.id}-progress"))
                    }
                    if (due != null) {
                        DueText(text = dueLabel(due, today), isOverdue = isOverdue, modifier = Modifier.testTag("task-${task.id}-due"))
                    }
                    if (task.status == TaskStatus.InProgress) {
                        TaskStatusPill(task.status)
                    }
                    TaskPriorityPill(task.priority)
                }
            }
            if (task.taskTags.isNotEmpty()) {
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
                    verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                ) {
                    task.taskTags.forEach { TaskTagPill(it) }
                }
            }
        }
    }
}

/** "3/5" with a checklist icon, read as "3 of 5 done". */
@Composable
fun ChecklistProgressText(progress: ChecklistProgress, modifier: Modifier = Modifier) {
    val description = pluralStringResource(R.plurals.tasks_checklist_progress_description, progress.total, progress.checked, progress.total)

    Row(
        modifier = modifier.clearAndSetSemantics { contentDescription = description },
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
    ) {
        Icon(
            painterResource(R.drawable.ic_list_check),
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(IconSize.xs),
        )
        Text(
            text = stringResource(R.string.tasks_checklist_progress, progress.checked, progress.total),
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

/** When a Task is due; red with "Overdue" once it has passed. */
@Composable
fun DueText(text: String, isOverdue: Boolean, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier,
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
    ) {
        val color = if (isOverdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant
        Icon(painterResource(R.drawable.ic_calendar), contentDescription = null, tint = color, modifier = Modifier.size(IconSize.xs))
        Text(
            text = if (isOverdue) stringResource(R.string.tasks_overdue_due, text) else text,
            style = MaterialTheme.typography.labelLarge,
            fontWeight = if (isOverdue) FontWeight.SemiBold else null,
            color = color,
            maxLines = 1,
        )
    }
}

/** Holds the cards' shape while the first page loads. */
@Composable
private fun TasksSkeleton(layout: TaskLayout) {
    val columns = if (layout == TaskLayout.Grid) 2 else 1
    val heights = listOf(96.dp, 140.dp, 72.dp, 120.dp, 88.dp, 104.dp)

    Row(
        modifier = Modifier.fillMaxSize().padding(horizontal = Spacing.gutter, vertical = Spacing.md).testTag("tasks-loading"),
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
    ) {
        repeat(columns) { column ->
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                heights.drop(column).take(6 / columns + 1).forEach { height ->
                    Skeleton(Modifier.fillMaxWidth().height(height), shape = MaterialTheme.shapes.large)
                }
            }
        }
    }
}

private fun TaskSectionKind.titleRes(): Int = when (this) {
    TaskSectionKind.Pinned -> R.string.tasks_section_pinned
    TaskSectionKind.Overdue -> R.string.tasks_section_overdue
    TaskSectionKind.Today -> R.string.tasks_section_today
    TaskSectionKind.Upcoming -> R.string.tasks_section_upcoming
    TaskSectionKind.NoDate -> R.string.tasks_section_no_date
}

private fun TaskTab.titleRes(): Int = when (this) {
    TaskTab.Open -> R.string.tasks_tab_open
    TaskTab.Done -> R.string.tasks_tab_done
}

@DrawableRes
private fun TaskTab.emptyIcon(): Int = when (this) {
    TaskTab.Open -> R.drawable.ic_circle_check
    TaskTab.Done -> R.drawable.ic_tasks
}

private fun TaskTab.emptyTitleRes(): Int = when (this) {
    TaskTab.Open -> R.string.tasks_empty_open_title
    TaskTab.Done -> R.string.tasks_empty_done_title
}

private fun TaskTab.emptyBodyRes(): Int = when (this) {
    TaskTab.Open -> R.string.tasks_empty_open_body
    TaskTab.Done -> R.string.tasks_empty_done_body
}
