package com.minthantzintun.trypost.feature.tasks

import android.text.format.DateFormat
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.ErrorMessage
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.taskTagRefs
import com.minthantzintun.trypost.core.ui.message
import java.time.Instant
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

@Composable
fun TaskEditorRoute(
    onBack: () -> Unit,
    onSaved: () -> Unit,
    viewModel: TaskEditorViewModel = hiltViewModel(),
    taskTagsViewModel: TaskTagsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val taskTagsState by taskTagsViewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(state.isSaved) {
        if (state.isSaved) {
            onSaved()
        }
    }

    // A Task tag deleted in the picker leaves the Task too.
    LaunchedEffect(taskTagsState.tagGroups) {
        taskTagsState.tagGroups?.let { groups ->
            viewModel.keepOnlyKnownTaskTags(groups.flatMap { group -> group.taskTags.map { it.id } }.toSet())
        }
    }

    TaskEditorScreen(
        state = state,
        taskTagsState = taskTagsState,
        onBack = onBack,
        onSave = viewModel::save,
        onRetry = viewModel::retry,
        onTitleChange = viewModel::changeTitle,
        onDescriptionChange = viewModel::changeDescription,
        onPriorityChange = viewModel::changePriority,
        onDueKindChange = viewModel::changeDueKind,
        onDueDateChange = viewModel::changeDueDate,
        onDueTimeChange = viewModel::changeDueTime,
        onToggleTaskTag = viewModel::toggleTaskTag,
        tagActions = TagActions(
            onRetry = taskTagsViewModel::load,
            onOpenDialog = taskTagsViewModel::openDialog,
            onCloseDialog = taskTagsViewModel::closeDialog,
            onSaveTagGroup = taskTagsViewModel::saveTagGroup,
            onSaveTaskTag = taskTagsViewModel::saveTaskTag,
            onConfirmDelete = taskTagsViewModel::confirmDelete,
        ),
    )
}

/** What the tag picker can ask of the Tag groups. */
data class TagActions(
    val onRetry: () -> Unit,
    val onOpenDialog: (TagDialog) -> Unit,
    val onCloseDialog: () -> Unit,
    val onSaveTagGroup: (String, TagColor) -> Unit,
    val onSaveTaskTag: (String) -> Unit,
    val onConfirmDelete: () -> Unit,
)

private enum class DueStep { None, Date, Time }

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun TaskEditorScreen(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    onBack: () -> Unit,
    onSave: () -> Unit,
    onRetry: () -> Unit,
    onTitleChange: (String) -> Unit,
    onDescriptionChange: (String) -> Unit,
    onPriorityChange: (TaskPriority) -> Unit,
    onDueKindChange: (DueKind) -> Unit,
    onDueDateChange: (LocalDate) -> Unit,
    onDueTimeChange: (LocalTime) -> Unit,
    onToggleTaskTag: (String) -> Unit,
    tagActions: TagActions,
) {
    var isConfirmingDiscard by rememberSaveable { mutableStateOf(false) }
    var isPickingTags by rememberSaveable { mutableStateOf(false) }
    var dueStep by rememberSaveable { mutableStateOf(DueStep.None) }
    val leave = {
        if (state.hasUnsavedChanges) {
            isConfirmingDiscard = true
        } else {
            onBack()
        }
    }

    BackHandler(enabled = state.hasUnsavedChanges, onBack = leave)

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(if (state.isNew) R.string.task_new_title else R.string.task_edit_title)) },
                navigationIcon = {
                    IconButton(onClick = leave, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
                actions = {
                    if (state.isReady) {
                        Button(
                            onClick = onSave,
                            enabled = !state.isSaving,
                            contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                            modifier = Modifier.padding(end = Spacing.sm).testTag("task-save"),
                        ) {
                            ButtonLabel(icon = R.drawable.ic_check, label = stringResource(R.string.save), isBusy = state.isSaving)
                        }
                    }
                },
            )
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                !state.isReady && state.loadError == null -> Column(
                    modifier = Modifier.fillMaxSize().padding(Spacing.gutter).testTag("task-editor-loading"),
                    verticalArrangement = Arrangement.spacedBy(Spacing.lg),
                ) {
                    Skeleton(Modifier.fillMaxWidth().height(56.dp), shape = MaterialTheme.shapes.small)
                    Skeleton(Modifier.fillMaxWidth().height(120.dp), shape = MaterialTheme.shapes.small)
                    Skeleton(Modifier.fillMaxWidth().height(40.dp), shape = MaterialTheme.shapes.small)
                }
                !state.isReady -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = R.drawable.ic_cloud_off,
                        title = stringResource(R.string.task_error_title),
                        body = state.loadError?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = onRetry,
                        isError = true,
                    )
                }
                else -> EditorForm(
                    state = state,
                    taskTagsState = taskTagsState,
                    onTitleChange = onTitleChange,
                    onDescriptionChange = onDescriptionChange,
                    onPriorityChange = onPriorityChange,
                    onDueKindChange = onDueKindChange,
                    onPickDate = { dueStep = DueStep.Date },
                    onPickTime = { dueStep = DueStep.Time },
                    onPickTags = { isPickingTags = true },
                    onToggleTaskTag = onToggleTaskTag,
                )
            }
        }
    }

    when (dueStep) {
        DueStep.None -> Unit
        DueStep.Date -> DueDateDialog(
            initial = state.form.due?.date ?: LocalDate.now(),
            onPicked = {
                dueStep = DueStep.None
                onDueDateChange(it)
            },
            onDismiss = { dueStep = DueStep.None },
        )
        DueStep.Time -> DueTimeDialog(
            initial = (state.form.due as? TaskDue.Timed)?.at?.toLocalTime() ?: LocalTime.now().withMinute(0).plusHours(1),
            onPicked = {
                dueStep = DueStep.None
                onDueTimeChange(it)
            },
            onDismiss = { dueStep = DueStep.None },
        )
    }

    if (isPickingTags) {
        TaskTagPickerSheet(
            state = taskTagsState,
            selectedIds = state.form.taskTagIds,
            onToggle = onToggleTaskTag,
            actions = tagActions,
            onDismiss = { isPickingTags = false },
        )
    }

    if (isConfirmingDiscard) {
        ConfirmDialog(
            title = stringResource(R.string.edit_discard_title),
            body = stringResource(R.string.edit_discard_body),
            confirmLabel = stringResource(R.string.edit_discard),
            onConfirm = {
                isConfirmingDiscard = false
                onBack()
            },
            onDismiss = { isConfirmingDiscard = false },
            dismissLabel = stringResource(R.string.edit_keep_editing),
            isDestructive = true,
            confirmTestTag = "task-discard",
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun EditorForm(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    onTitleChange: (String) -> Unit,
    onDescriptionChange: (String) -> Unit,
    onPriorityChange: (TaskPriority) -> Unit,
    onDueKindChange: (DueKind) -> Unit,
    onPickDate: () -> Unit,
    onPickTime: () -> Unit,
    onPickTags: () -> Unit,
    onToggleTaskTag: (String) -> Unit,
) {
    val form = state.form
    val errors = state.fieldErrors

    Column(
        modifier = Modifier
            .fillMaxSize()
            .imePadding()
            .verticalScroll(rememberScrollState())
            .padding(Spacing.gutter),
        verticalArrangement = Arrangement.spacedBy(Spacing.lg),
    ) {
        state.saveError?.let {
            ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("task-save-error"))
        }

        FormField(
            value = form.title,
            onValueChange = onTitleChange,
            label = stringResource(R.string.task_title),
            enabled = !state.isSaving,
            isError = errors.containsKey(TaskField.Title),
            supportingText = errors[TaskField.Title],
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            modifier = Modifier.testTag("task-title-field"),
        )

        FormField(
            value = form.description,
            onValueChange = onDescriptionChange,
            label = stringResource(R.string.task_description),
            singleLine = false,
            minLines = 4,
            enabled = !state.isSaving,
            isError = errors.containsKey(TaskField.Description),
            supportingText = errors[TaskField.Description],
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            modifier = Modifier.testTag("task-description-field"),
        )

        EditorSection(title = stringResource(R.string.task_priority), error = errors[TaskField.Priority]) {
            SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth().testTag("task-priority")) {
                TaskPriority.entries.forEachIndexed { index, priority ->
                    SegmentedButton(
                        selected = form.priority == priority,
                        onClick = { onPriorityChange(priority) },
                        enabled = !state.isSaving,
                        shape = SegmentedButtonDefaults.itemShape(index, TaskPriority.entries.size),
                        modifier = Modifier.testTag("task-priority-${priority.name.lowercase()}"),
                    ) { Text(stringResource(priority.labelRes()), maxLines = 1) }
                }
            }
        }

        EditorSection(title = stringResource(R.string.task_due), error = errors[TaskField.Due]) {
            SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth().testTag("task-due-kind")) {
                DueKind.entries.forEachIndexed { index, kind ->
                    SegmentedButton(
                        selected = form.dueKind == kind,
                        onClick = { onDueKindChange(kind) },
                        enabled = !state.isSaving,
                        shape = SegmentedButtonDefaults.itemShape(index, DueKind.entries.size),
                        modifier = Modifier.testTag("task-due-${kind.name.lowercase()}"),
                    ) { Text(stringResource(kind.labelRes()), maxLines = 1) }
                }
            }
            form.due?.let { due ->
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    OutlinedButton(
                        onClick = onPickDate,
                        enabled = !state.isSaving,
                        contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                        modifier = Modifier.weight(1f).testTag("task-due-date"),
                    ) {
                        ButtonLabel(icon = R.drawable.ic_calendar, label = due.date.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM)))
                    }
                    if (due is TaskDue.Timed) {
                        OutlinedButton(
                            onClick = onPickTime,
                            enabled = !state.isSaving,
                            contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                            modifier = Modifier.weight(1f).testTag("task-due-time"),
                        ) {
                            ButtonLabel(icon = R.drawable.ic_clock_plus, label = due.at.toLocalTime().format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT)))
                        }
                    }
                }
            }
        }

        EditorSection(title = stringResource(R.string.tags_title), error = errors[TaskField.TaskTags]) {
            val refs = remember(taskTagsState.tagGroups) { taskTagsState.tagGroups.orEmpty().taskTagRefs() }
            val picked = form.taskTagIds.mapNotNull { refs[it] }

            if (picked.isNotEmpty()) {
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
                    verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                    modifier = Modifier.testTag("task-tags-picked"),
                ) {
                    picked.forEach { TaskTagPill(it) }
                }
            }
            OutlinedButton(
                onClick = onPickTags,
                enabled = !state.isSaving,
                contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                modifier = Modifier.testTag("task-pick-tags"),
            ) {
                ButtonLabel(
                    icon = R.drawable.ic_tag,
                    label = stringResource(if (form.taskTagIds.isEmpty()) R.string.task_add_tags else R.string.task_change_tags),
                )
            }
        }
    }
}

@Composable
private fun EditorSection(title: String, error: String?, content: @Composable () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        Text(title, style = MaterialTheme.typography.titleSmall, modifier = Modifier.semantics { heading() })
        content()
        error?.let { ErrorMessage(lines = listOf(it)) }
    }
}

private fun DueKind.labelRes(): Int = when (this) {
    DueKind.None -> R.string.task_due_none
    DueKind.Date -> R.string.task_due_date
    DueKind.DateTime -> R.string.task_due_date_time
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DueDateDialog(initial: LocalDate, onPicked: (LocalDate) -> Unit, onDismiss: () -> Unit) {
    // DatePicker speaks in UTC midnights, so dates convert through UTC, not the phone's zone.
    // Past days stay selectable: a Task can be due on a day already gone.
    val state = rememberDatePickerState(initialSelectedDateMillis = initial.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli())

    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(
                onClick = {
                    state.selectedDateMillis?.let { onPicked(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate()) }
                },
                enabled = state.selectedDateMillis != null,
                modifier = Modifier.testTag("task-due-date-ok"),
            ) { Text(stringResource(R.string.ok)) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    ) {
        DatePicker(state = state)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DueTimeDialog(initial: LocalTime, onPicked: (LocalTime) -> Unit, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val state = rememberTimePickerState(
        initialHour = initial.hour,
        initialMinute = initial.minute,
        is24Hour = DateFormat.is24HourFormat(context),
    )

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.schedule_pick_time)) },
        text = { TimePicker(state = state) },
        confirmButton = {
            Button(
                onClick = { onPicked(LocalTime.of(state.hour, state.minute)) },
                modifier = Modifier.testTag("task-due-time-ok"),
            ) { Text(stringResource(R.string.ok)) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}
