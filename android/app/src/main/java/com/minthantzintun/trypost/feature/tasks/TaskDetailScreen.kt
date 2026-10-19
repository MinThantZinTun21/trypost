package com.minthantzintun.trypost.feature.tasks

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.ErrorMessage
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Panel
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskBody
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.ui.message
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

@Composable
fun TaskDetailRoute(
    onBack: () -> Unit,
    onEdit: (String) -> Unit,
    viewModel: TaskDetailViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(state.isDeleted) {
        if (state.isDeleted) {
            onBack()
        }
    }

    TaskDetailScreen(
        state = state,
        onBack = onBack,
        onEdit = onEdit,
        onRefresh = viewModel::refresh,
        onRetry = viewModel::retry,
        onSetStatus = viewModel::setStatus,
        onToggleCheckbox = viewModel::toggleCheckbox,
        onDelete = viewModel::delete,
        extras = TaskExtrasActions(
            onSaveReminders = viewModel::saveReminders,
            onStartTimer = viewModel::startTimer,
            onStopTimer = viewModel::stopTimer,
            attachments = AttachmentActions(
                onAttach = viewModel::attach,
                onRetryUpload = viewModel::retryUpload,
                onDismissUpload = viewModel::dismissUpload,
                onDelete = viewModel::deleteAttachment,
            ),
        ),
    )
}

/** What the reminders, timer and attachments on the detail screen can ask for. */
data class TaskExtrasActions(
    val onSaveReminders: (List<ReminderDraft>) -> Unit,
    val onStartTimer: (TimerMode) -> Unit,
    val onStopTimer: () -> Unit,
    val attachments: AttachmentActions,
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TaskDetailScreen(
    state: TaskDetailUiState,
    onBack: () -> Unit,
    onEdit: (String) -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    onSetStatus: (TaskStatus) -> Unit,
    onToggleCheckbox: (Int) -> Unit,
    onDelete: () -> Unit,
    extras: TaskExtrasActions,
) {
    var isConfirmingDelete by rememberSaveable { mutableStateOf(false) }
    val task = state.task
    val containerColor = task?.color?.tone()?.container ?: MaterialTheme.colorScheme.surface

    Scaffold(
        containerColor = containerColor,
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.task_detail_title)) },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = containerColor),
                navigationIcon = {
                    IconButton(onClick = onBack, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
                actions = {
                    if (task != null) {
                        ActionIconButton(
                            icon = R.drawable.ic_edit,
                            label = stringResource(R.string.task_edit),
                            onClick = { onEdit(task.id) },
                            enabled = !state.isDeleting,
                            modifier = Modifier.testTag("task-edit"),
                        )
                        ActionIconButton(
                            icon = R.drawable.ic_delete,
                            label = stringResource(R.string.task_delete),
                            onClick = { isConfirmingDelete = true },
                            enabled = !state.isDeleting && state.savingStatus == null,
                            isBusy = state.isDeleting,
                            modifier = Modifier.testTag("task-delete"),
                        )
                    }
                },
            )
        },
        bottomBar = {
            if (task != null) {
                StatusBar(task = task, state = state, onSetStatus = onSetStatus)
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            val error = state.error
            when {
                task == null && state.isLoading -> Column(
                    modifier = Modifier.fillMaxSize().padding(Spacing.gutter).testTag("task-loading"),
                    verticalArrangement = Arrangement.spacedBy(Spacing.md),
                ) {
                    Skeleton(Modifier.width(96.dp).height(24.dp))
                    Skeleton(Modifier.fillMaxWidth(0.8f).height(28.dp))
                    Skeleton(Modifier.fillMaxWidth().height(120.dp), shape = MaterialTheme.shapes.large)
                }
                task == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = R.drawable.ic_cloud_off,
                        title = stringResource(R.string.task_error_title),
                        body = error?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = onRetry,
                        isError = true,
                        modifier = Modifier.testTag("task-error"),
                    )
                }
                else -> PullToRefreshBox(
                    isRefreshing = state.isRefreshing,
                    onRefresh = onRefresh,
                    modifier = Modifier.fillMaxSize(),
                ) {
                    TaskDetails(task, state = state, onToggleCheckbox = onToggleCheckbox, extras = extras)
                }
            }
        }
    }

    if (isConfirmingDelete) {
        ConfirmDialog(
            title = stringResource(R.string.task_delete_title),
            body = stringResource(R.string.task_delete_body),
            confirmLabel = stringResource(R.string.task_delete),
            onConfirm = {
                isConfirmingDelete = false
                onDelete()
            },
            onDismiss = { isConfirmingDelete = false },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_delete,
            isDestructive = true,
            confirmTestTag = "task-delete-confirm",
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TaskDetails(task: Task, state: TaskDetailUiState, onToggleCheckbox: (Int) -> Unit, extras: TaskExtrasActions) {
    val zone = remember { ZoneId.systemDefault() }
    val now = remember(task) { Instant.now() }
    val today = now.atZone(zone).toLocalDate()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(Spacing.gutter),
        verticalArrangement = Arrangement.spacedBy(Spacing.lg),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                itemVerticalAlignment = Alignment.CenterVertically,
            ) {
                TaskStatusPill(task.status, modifier = Modifier.testTag("task-status"))
                TaskPriorityPill(task.priority)
                if (task.isPinned) {
                    Icon(
                        painter = painterResource(R.drawable.ic_pin),
                        contentDescription = stringResource(R.string.task_pinned),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(IconSize.sm),
                    )
                }
            }
            task.title?.let { title ->
                SelectionContainer {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.headlineSmall,
                        modifier = Modifier.semantics { heading() }.testTag("task-title"),
                    )
                }
            }
            val description = task.description
            if (!description.isNullOrBlank()) {
                val body = remember(description) { TaskBody.parse(description) }
                val checklist = remember(body) { body.checklist() }
                checklist?.let { ChecklistProgressText(it, modifier = Modifier.testTag("task-progress")) }
                SelectionContainer {
                    TaskBodyBlocks(
                        blocks = body.blocks,
                        isCompact = false,
                        onToggle = onToggleCheckbox,
                        modifier = Modifier.fillMaxWidth().testTag("task-description"),
                    )
                }
            }
        }

        Panel {
            Column(modifier = Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
                DetailRow(label = stringResource(R.string.task_due)) {
                    val due = task.due(zone)
                    if (due == null) {
                        Text(stringResource(R.string.task_no_due), style = MaterialTheme.typography.bodyMedium)
                    } else {
                        DueText(text = dueLabel(due, today), isOverdue = task.isOverdue(now, zone), modifier = Modifier.testTag("task-due"))
                    }
                }
                DetailRow(label = stringResource(R.string.task_priority)) {
                    Text(stringResource(task.priority.labelRes()), style = MaterialTheme.typography.bodyMedium)
                }
                DetailRow(label = stringResource(R.string.tags_title)) {
                    if (task.taskTags.isEmpty()) {
                        Text(stringResource(R.string.task_no_tags), style = MaterialTheme.typography.bodyMedium)
                    } else {
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(Spacing.xs), verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                            task.taskTags.forEach { TaskTagPill(it) }
                        }
                    }
                }
            }
        }

        Panel(modifier = Modifier.testTag("task-reminders-panel")) {
            Column(modifier = Modifier.padding(Spacing.lg)) {
                DetailRow(label = stringResource(R.string.task_reminders)) {
                    ReminderEditor(
                        reminders = remember(task.reminders, zone) { task.reminders.map { it.toDraft(zone) } },
                        due = task.due(zone),
                        onChange = extras.onSaveReminders,
                        enabled = !state.isSavingReminders && !state.isDeleting,
                        dismissed = remember(task.reminders) { task.reminders.indices.filter { task.reminders[it].dismissedAt != null }.toSet() },
                    )
                }
            }
        }

        Panel(modifier = Modifier.testTag("task-timer-panel")) {
            Column(modifier = Modifier.padding(Spacing.lg)) {
                DetailRow(label = stringResource(R.string.task_timer)) {
                    TimerSection(task = task, state = state, onStart = extras.onStartTimer, onStop = extras.onStopTimer)
                }
            }
        }

        Panel(modifier = Modifier.testTag("task-attachments-panel")) {
            Column(modifier = Modifier.padding(Spacing.lg)) {
                DetailRow(label = stringResource(R.string.task_attachments)) {
                    AttachmentsSection(
                        attachments = task.attachments,
                        uploads = state.uploads,
                        deletingId = state.deletingAttachmentId,
                        actions = extras.attachments,
                    )
                }
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
            task.completedAt?.let {
                Text(
                    text = stringResource(R.string.task_completed_at, dateTimeLabel(it, zone, today)),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.testTag("task-completed-at"),
                )
            }
            task.createdAt?.let {
                Text(
                    text = stringResource(R.string.task_created_at, dateTimeLabel(it, zone, today)),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun DetailRow(label: String, content: @Composable () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        content()
    }
}

@Composable
private fun dateTimeLabel(at: Instant, zone: ZoneId, today: LocalDate): String {
    val local = at.atZone(zone).toLocalDateTime()

    return stringResource(
        R.string.tasks_due_day_time,
        dayLabel(local.toLocalDate(), today),
        local.toLocalTime().format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT)),
    )
}

/** Done or Reopen, and In progress or To do, each one tap through the status endpoint. */
@Composable
private fun StatusBar(task: Task, state: TaskDetailUiState, onSetStatus: (TaskStatus) -> Unit) {
    val isBusy = state.savingStatus != null || state.isDeleting

    Surface(color = MaterialTheme.colorScheme.surfaceContainer, modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .navigationBarsPadding()
                .padding(horizontal = Spacing.gutter, vertical = Spacing.md),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            state.actionError?.let {
                ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("task-action-error"))
            }
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                if (task.status == TaskStatus.Done) {
                    Button(
                        onClick = { onSetStatus(TaskStatus.Todo) },
                        enabled = !isBusy,
                        contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                        modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("task-reopen"),
                    ) {
                        ButtonLabel(icon = R.drawable.ic_reopen, label = stringResource(R.string.task_reopen), isBusy = state.savingStatus == TaskStatus.Todo)
                    }
                } else {
                    Button(
                        onClick = { onSetStatus(TaskStatus.Done) },
                        enabled = !isBusy,
                        contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                        modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("task-done"),
                    ) {
                        ButtonLabel(icon = R.drawable.ic_circle_check, label = stringResource(R.string.task_mark_done), isBusy = state.savingStatus == TaskStatus.Done)
                    }
                    val next = if (task.status == TaskStatus.InProgress) TaskStatus.Todo else TaskStatus.InProgress
                    FilledTonalButton(
                        onClick = { onSetStatus(next) },
                        enabled = !isBusy,
                        contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                        modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("task-toggle-progress"),
                    ) {
                        ButtonLabel(
                            icon = next.icon(),
                            label = stringResource(if (next == TaskStatus.InProgress) R.string.task_start else R.string.task_back_to_todo),
                            isBusy = state.savingStatus == next,
                        )
                    }
                }
            }
        }
    }
}
