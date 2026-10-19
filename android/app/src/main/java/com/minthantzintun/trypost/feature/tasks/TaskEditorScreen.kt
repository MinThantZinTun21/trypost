package com.minthantzintun.trypost.feature.tasks

import android.text.format.DateFormat
import androidx.activity.compose.BackHandler
import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.AssistChip
import androidx.compose.material3.AssistChipDefaults
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconToggleButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberModalBottomSheetState
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.ErrorMessage
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.InlineStyle
import com.minthantzintun.trypost.core.model.ReminderDraft
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
    onClose: () -> Unit,
    onDeleted: () -> Unit,
    viewModel: TaskEditorViewModel = hiltViewModel(),
    taskTagsViewModel: TaskTagsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val taskTagsState by taskTagsViewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(state.isClosed, state.isDeleted) {
        when {
            state.isDeleted -> onDeleted()
            state.isClosed -> onClose()
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
        actions = TaskEditorActions(
            onLeave = viewModel::leave,
            onDiscard = viewModel::discard,
            onDelete = viewModel::delete,
            onRetry = viewModel::retry,
            onTitleChange = viewModel::changeTitle,
            onTogglePin = viewModel::togglePin,
            onColorChange = viewModel::changeColor,
            onPriorityChange = viewModel::changePriority,
            onDueKindChange = viewModel::changeDueKind,
            onDueDateChange = viewModel::changeDueDate,
            onDueTimeChange = viewModel::changeDueTime,
            onToggleTaskTag = viewModel::toggleTaskTag,
            onRemindersChange = viewModel::changeReminders,
            onToggleBlockKind = viewModel::toggleBlockKind,
            onToggleStyle = viewModel::toggleStyle,
            body = BodyEditorActions(
                onTextChange = viewModel::changeBlockText,
                onSelect = viewModel::selectInBody,
                onBackspaceAtStart = viewModel::backspaceAtBlockStart,
                onToggleChecked = viewModel::toggleChecked,
            ),
        ),
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

/** What the editor screen can ask of its ViewModel. */
data class TaskEditorActions(
    val onLeave: () -> Unit,
    val onDiscard: () -> Unit,
    val onDelete: () -> Unit,
    val onRetry: () -> Unit,
    val onTitleChange: (String) -> Unit,
    val onTogglePin: () -> Unit,
    val onColorChange: (TagColor?) -> Unit,
    val onPriorityChange: (TaskPriority) -> Unit,
    val onDueKindChange: (DueKind) -> Unit,
    val onDueDateChange: (LocalDate) -> Unit,
    val onDueTimeChange: (LocalTime) -> Unit,
    val onToggleTaskTag: (String) -> Unit,
    val onRemindersChange: (List<ReminderDraft>) -> Unit,
    val onToggleBlockKind: (BlockKind) -> Unit,
    val onToggleStyle: (InlineStyle) -> Unit,
    val body: BodyEditorActions,
)

private enum class DueStep { None, Date, Time }

/** The bottom bar's open panel: none, the formatting row (Aa) or the options sheet (+). */
private enum class EditorPanel { None, Format, Options }

/**
 * The Keep-style editor: a title, then the body, on the Task's colour.
 * Back saves; ⋮ holds Delete and Discard changes; + opens the due,
 * priority, tags and colour; Aa the headings, styles and lists.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TaskEditorScreen(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    actions: TaskEditorActions,
    tagActions: TagActions,
) {
    var isConfirmingDelete by rememberSaveable { mutableStateOf(false) }
    var dueStep by rememberSaveable { mutableStateOf(DueStep.None) }
    var panel by rememberSaveable { mutableStateOf(EditorPanel.None) }
    val containerColor = state.form.color?.tone()?.container ?: MaterialTheme.colorScheme.surface
    val isBusy = state.isSaving || state.isDeleting

    BackHandler(onBack = actions.onLeave)

    Scaffold(
        containerColor = containerColor,
        topBar = {
            TopAppBar(
                title = {},
                colors = TopAppBarDefaults.topAppBarColors(containerColor = containerColor),
                navigationIcon = {
                    IconButton(onClick = actions.onLeave, enabled = !isBusy, modifier = Modifier.testTag("back")) {
                        if (state.isSaving) {
                            CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
                        } else {
                            Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                        }
                    }
                },
                actions = {
                    if (state.isReady) {
                        EditorTopActions(
                            state = state,
                            isBusy = isBusy,
                            onTogglePin = actions.onTogglePin,
                            onAskDelete = { isConfirmingDelete = true },
                            onDiscard = actions.onDiscard,
                        )
                    }
                },
            )
        },
        bottomBar = {
            if (state.isReady) {
                EditorBottomBar(
                    state = state,
                    containerColor = containerColor,
                    panel = panel,
                    onPanelChange = { panel = it },
                    onToggleBlockKind = actions.onToggleBlockKind,
                    onToggleStyle = actions.onToggleStyle,
                )
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                !state.isReady && state.loadError == null -> Column(
                    modifier = Modifier.fillMaxSize().padding(Spacing.gutter).testTag("task-editor-loading"),
                    verticalArrangement = Arrangement.spacedBy(Spacing.lg),
                ) {
                    Skeleton(Modifier.fillMaxWidth(0.6f).height(32.dp), shape = MaterialTheme.shapes.small)
                    Skeleton(Modifier.fillMaxWidth().height(120.dp), shape = MaterialTheme.shapes.small)
                }
                !state.isReady -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = R.drawable.ic_cloud_off,
                        title = stringResource(R.string.task_error_title),
                        body = state.loadError?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = actions.onRetry,
                        isError = true,
                    )
                }
                else -> EditorContent(
                    state = state,
                    taskTagsState = taskTagsState,
                    enabled = !isBusy,
                    onTitleChange = actions.onTitleChange,
                    body = actions.body,
                    onOpenOptions = { panel = EditorPanel.Options },
                )
            }
        }
    }

    if (panel == EditorPanel.Options) {
        OptionsSheet(
            state = state,
            taskTagsState = taskTagsState,
            actions = actions,
            tagActions = tagActions,
            onPickDate = { dueStep = DueStep.Date },
            onPickTime = { dueStep = DueStep.Time },
            onDismiss = { panel = EditorPanel.None },
        )
    }

    when (dueStep) {
        DueStep.None -> Unit
        DueStep.Date -> DueDateDialog(
            initial = state.form.due?.date ?: LocalDate.now(),
            onPicked = {
                dueStep = DueStep.None
                actions.onDueDateChange(it)
            },
            onDismiss = { dueStep = DueStep.None },
        )
        DueStep.Time -> DueTimeDialog(
            initial = (state.form.due as? TaskDue.Timed)?.at?.toLocalTime() ?: LocalTime.now().withMinute(0).plusHours(1),
            onPicked = {
                dueStep = DueStep.None
                actions.onDueTimeChange(it)
            },
            onDismiss = { dueStep = DueStep.None },
            onRemove = (state.form.due as? TaskDue.Timed)?.let {
                {
                    dueStep = DueStep.None
                    actions.onDueKindChange(DueKind.Date)
                }
            },
        )
    }

    if (isConfirmingDelete) {
        ConfirmDialog(
            title = stringResource(R.string.task_delete_title),
            body = stringResource(R.string.task_delete_body),
            confirmLabel = stringResource(R.string.task_delete),
            onConfirm = {
                isConfirmingDelete = false
                actions.onDelete()
            },
            onDismiss = { isConfirmingDelete = false },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_delete,
            isDestructive = true,
            confirmTestTag = "task-delete-confirm",
        )
    }
}

@Composable
private fun EditorTopActions(
    state: TaskEditorUiState,
    isBusy: Boolean,
    onTogglePin: () -> Unit,
    onAskDelete: () -> Unit,
    onDiscard: () -> Unit,
) {
    var isMenuOpen by remember { mutableStateOf(false) }

    IconToggleButton(
        checked = state.form.isPinned,
        onCheckedChange = { onTogglePin() },
        enabled = !isBusy,
        modifier = Modifier.testTag("task-pin"),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_pin),
            contentDescription = stringResource(if (state.form.isPinned) R.string.task_unpin else R.string.task_pin),
            tint = if (state.form.isPinned) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
    Box {
        ActionIconButton(
            icon = R.drawable.ic_more,
            label = stringResource(R.string.task_more),
            onClick = { isMenuOpen = true },
            enabled = !isBusy,
            isBusy = state.isDeleting,
            modifier = Modifier.testTag("task-more"),
        )
        DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
            if (!state.isNew) {
                DropdownMenuItem(
                    text = { Text(stringResource(R.string.task_delete)) },
                    leadingIcon = { Icon(painterResource(R.drawable.ic_delete), contentDescription = null) },
                    onClick = {
                        isMenuOpen = false
                        onAskDelete()
                    },
                    modifier = Modifier.testTag("task-menu-delete"),
                )
            }
            DropdownMenuItem(
                text = { Text(stringResource(R.string.task_discard_changes)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_close), contentDescription = null) },
                onClick = {
                    isMenuOpen = false
                    onDiscard()
                },
                modifier = Modifier.testTag("task-menu-discard"),
            )
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun EditorContent(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    enabled: Boolean,
    onTitleChange: (String) -> Unit,
    body: BodyEditorActions,
    onOpenOptions: () -> Unit,
) {
    val form = state.form
    val errors = state.fieldErrors
    // Errors of fields that live in the + sheet show here too, so a refused Back says why.
    val otherErrors = listOfNotNull(errors[TaskField.Description], errors[TaskField.Due], errors[TaskField.Priority], errors[TaskField.TaskTags])

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = Spacing.gutter, vertical = Spacing.sm),
        verticalArrangement = Arrangement.spacedBy(Spacing.md),
    ) {
        state.saveError?.let {
            ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("task-save-error"))
        }

        Column(verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
            TitleField(value = form.title, onValueChange = onTitleChange, enabled = enabled)
            errors[TaskField.Title]?.let { ErrorMessage(lines = listOf(it), modifier = Modifier.testTag("task-title-error")) }
        }

        BodyEditor(body = state.body, actions = body, enabled = enabled)

        if (otherErrors.isNotEmpty()) {
            ErrorMessage(lines = otherErrors, modifier = Modifier.testTag("task-field-errors"))
        }

        val refs = remember(taskTagsState.tagGroups) { taskTagsState.tagGroups.orEmpty().taskTagRefs() }
        val picked = form.taskTagIds.mapNotNull { refs[it] }
        if (form.due != null || form.priority != TaskPriority.None || picked.isNotEmpty()) {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
                verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                modifier = Modifier
                    .clickable(enabled = enabled, onClickLabel = stringResource(R.string.task_options), onClick = onOpenOptions)
                    .testTag("task-options-summary"),
            ) {
                form.due?.let { due ->
                    DueText(text = dueLabel(due, LocalDate.now()), isOverdue = false)
                }
                TaskPriorityPill(form.priority)
                picked.forEach { TaskTagPill(it) }
            }
        }
    }
}

@Composable
private fun TitleField(value: String, onValueChange: (String) -> Unit, enabled: Boolean) {
    val style = MaterialTheme.typography.headlineSmall.copy(color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.SemiBold)

    Box(contentAlignment = Alignment.CenterStart) {
        if (value.isEmpty()) {
            Text(stringResource(R.string.task_title), style = style.copy(color = MaterialTheme.colorScheme.onSurfaceVariant))
        }
        BasicTextField(
            value = value,
            onValueChange = { onValueChange(it.replace('\n', ' ')) },
            enabled = enabled,
            textStyle = style,
            cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            modifier = Modifier.fillMaxWidth().testTag("task-title-field"),
        )
    }
}

/** The + and Aa buttons, with the formatting row above them while Aa is open. */
@Composable
private fun EditorBottomBar(
    state: TaskEditorUiState,
    containerColor: Color,
    panel: EditorPanel,
    onPanelChange: (EditorPanel) -> Unit,
    onToggleBlockKind: (BlockKind) -> Unit,
    onToggleStyle: (InlineStyle) -> Unit,
) {
    Surface(color = containerColor, modifier = Modifier.fillMaxWidth().navigationBarsPadding().imePadding()) {
        Column {
            HorizontalDivider()
            if (panel == EditorPanel.Format) {
                FormatRow(state = state, onToggleBlockKind = onToggleBlockKind, onToggleStyle = onToggleStyle)
            }
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = Spacing.xs)) {
                ActionIconButton(
                    icon = R.drawable.ic_plus,
                    label = stringResource(R.string.task_options),
                    onClick = { onPanelChange(EditorPanel.Options) },
                    modifier = Modifier.testTag("task-options"),
                )
                IconToggleButton(
                    checked = panel == EditorPanel.Format,
                    onCheckedChange = { onPanelChange(if (it) EditorPanel.Format else EditorPanel.None) },
                    modifier = Modifier.testTag("task-format"),
                ) {
                    Icon(painterResource(R.drawable.ic_text_size), contentDescription = stringResource(R.string.task_format))
                }
                Spacer(Modifier.weight(1f))
            }
        }
    }
}

private data class FormatButton(@DrawableRes val icon: Int, @StringRes val label: Int, val tag: String)

@Composable
private fun FormatRow(state: TaskEditorUiState, onToggleBlockKind: (BlockKind) -> Unit, onToggleStyle: (InlineStyle) -> Unit) {
    val body = state.body
    val enabled = body.selection != null && !state.isSaving && !state.isDeleting
    val kinds = listOf(
        BlockKind.Heading1 to FormatButton(R.drawable.ic_h1, R.string.task_format_h1, "h1"),
        BlockKind.Heading2 to FormatButton(R.drawable.ic_h2, R.string.task_format_h2, "h2"),
    )
    val styles = listOf(
        InlineStyle.Bold to FormatButton(R.drawable.ic_bold, R.string.task_format_bold, "bold"),
        InlineStyle.Italic to FormatButton(R.drawable.ic_italic, R.string.task_format_italic, "italic"),
        InlineStyle.Strike to FormatButton(R.drawable.ic_strikethrough, R.string.task_format_strike, "strike"),
    )
    val lists = listOf(
        BlockKind.Bullet to FormatButton(R.drawable.ic_list, R.string.task_format_bullet, "bullet"),
        BlockKind.Numbered to FormatButton(R.drawable.ic_list_numbers, R.string.task_format_numbered, "numbered"),
        BlockKind.Checkbox to FormatButton(R.drawable.ic_square_check, R.string.task_format_checkbox, "checkbox"),
    )

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = Spacing.xs)
            .testTag("task-format-row"),
    ) {
        kinds.forEach { (kind, button) ->
            FormatToggle(button, isOn = body.currentKind == kind, enabled = enabled) { onToggleBlockKind(kind) }
        }
        styles.forEach { (style, button) ->
            FormatToggle(button, isOn = style in body.activeStyles, enabled = enabled) { onToggleStyle(style) }
        }
        lists.forEach { (kind, button) ->
            FormatToggle(button, isOn = body.currentKind == kind, enabled = enabled) { onToggleBlockKind(kind) }
        }
    }
}

@Composable
private fun FormatToggle(button: FormatButton, isOn: Boolean, enabled: Boolean, onClick: () -> Unit) {
    IconToggleButton(
        checked = isOn,
        onCheckedChange = { onClick() },
        enabled = enabled,
        modifier = Modifier.testTag("task-format-${button.tag}"),
    ) {
        Icon(painterResource(button.icon), contentDescription = stringResource(button.label))
    }
}

/**
 * The + sheet: due, reminders, priority, Task tags and the card's colour,
 * each one compact row led by its icon. The Task tags are picked, added and
 * managed right here: a tag form or delete confirm opens as a page of this
 * sheet, never as a second sheet or dialog over it, which could not take
 * taps or focus while this one closed.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun OptionsSheet(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    actions: TaskEditorActions,
    tagActions: TagActions,
    onPickDate: () -> Unit,
    onPickTime: () -> Unit,
    onDismiss: () -> Unit,
) {
    val dialog = taskTagsState.dialog

    ModalBottomSheet(
        onDismissRequest = {
            tagActions.onCloseDialog()
            onDismiss()
        },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        modifier = Modifier.testTag("task-options-sheet"),
    ) {
        BackHandler(enabled = dialog != null, onBack = tagActions.onCloseDialog)

        if (dialog == null) {
            OptionsPage(state, taskTagsState, actions, tagActions, onPickDate, onPickTime)
        } else {
            TagDialogPage(dialog, taskTagsState, tagActions)
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun OptionsPage(
    state: TaskEditorUiState,
    taskTagsState: TaskTagsUiState,
    actions: TaskEditorActions,
    tagActions: TagActions,
    onPickDate: () -> Unit,
    onPickTime: () -> Unit,
) {
    val form = state.form
    val errors = state.fieldErrors
    val enabled = !state.isSaving && !state.isDeleting
    var isManagingTags by rememberSaveable { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .verticalScroll(rememberScrollState())
            .padding(start = Spacing.gutter, end = Spacing.gutter, bottom = Spacing.xl),
        verticalArrangement = Arrangement.spacedBy(Spacing.md),
    ) {
        OptionRow(icon = R.drawable.ic_calendar, label = stringResource(R.string.task_due), error = errors[TaskField.Due]) {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
                itemVerticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.testTag("task-due"),
            ) {
                val due = form.due
                AssistChip(
                    onClick = onPickDate,
                    enabled = enabled,
                    label = {
                        Text(
                            text = due?.date?.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM)) ?: stringResource(R.string.task_due_add_date),
                            maxLines = 1,
                        )
                    },
                    modifier = Modifier.testTag("task-due-date"),
                )
                if (due != null) {
                    AssistChip(
                        onClick = onPickTime,
                        enabled = enabled,
                        label = {
                            Text(
                                text = (due as? TaskDue.Timed)?.at?.toLocalTime()?.format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT))
                                    ?: stringResource(R.string.task_due_add_time),
                                maxLines = 1,
                            )
                        },
                        leadingIcon = { Icon(painterResource(R.drawable.ic_clock), contentDescription = null, modifier = Modifier.size(AssistChipDefaults.IconSize)) },
                        modifier = Modifier.testTag("task-due-time"),
                    )
                    ActionIconButton(
                        icon = R.drawable.ic_close,
                        label = stringResource(R.string.task_due_clear),
                        onClick = { actions.onDueKindChange(DueKind.None) },
                        enabled = enabled,
                        modifier = Modifier.testTag("task-due-clear"),
                    )
                }
            }
        }

        OptionRow(icon = R.drawable.ic_flag, label = stringResource(R.string.task_priority), error = errors[TaskField.Priority]) {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(Spacing.xs), modifier = Modifier.testTag("task-priority")) {
                TaskPriority.entries.forEach { priority ->
                    val tone = priority.tone()
                    FilterChip(
                        selected = form.priority == priority,
                        onClick = { actions.onPriorityChange(priority) },
                        enabled = enabled,
                        label = { Text(stringResource(priority.labelRes()), maxLines = 1) },
                        colors = FilterChipDefaults.filterChipColors(selectedContainerColor = tone.container, selectedLabelColor = tone.content),
                        modifier = Modifier.testTag("task-priority-${priority.name.lowercase()}"),
                    )
                }
            }
        }

        OptionRow(icon = R.drawable.ic_bell, label = stringResource(R.string.task_reminders), error = errors[TaskField.Reminders]) {
            ReminderEditor(
                reminders = form.reminders,
                due = form.due,
                onChange = actions.onRemindersChange,
                enabled = enabled,
            )
        }

        OptionRow(icon = R.drawable.ic_tag, label = stringResource(R.string.tags_title), error = errors[TaskField.TaskTags]) {
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs), modifier = Modifier.testTag("task-tags")) {
                if (isManagingTags) {
                    Text(
                        text = stringResource(R.string.tags_manage_hint),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                TagGroupsContent(
                    state = taskTagsState,
                    selectedIds = form.taskTagIds,
                    onToggle = actions.onToggleTaskTag,
                    onRetry = tagActions.onRetry,
                    isManaging = isManagingTags,
                    onOpenDialog = tagActions.onOpenDialog,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                    TextButton(
                        onClick = { tagActions.onOpenDialog(TagDialog.NewTagGroup) },
                        contentPadding = ButtonDefaults.TextButtonWithIconContentPadding,
                        modifier = Modifier.testTag("new-tag-group"),
                    ) {
                        Icon(painterResource(R.drawable.ic_plus), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                        Text(stringResource(R.string.tags_new_group), modifier = Modifier.padding(start = Spacing.xs))
                    }
                    if (!taskTagsState.tagGroups.isNullOrEmpty()) {
                        TextButton(onClick = { isManagingTags = !isManagingTags }, modifier = Modifier.testTag("tags-manage")) {
                            Text(stringResource(if (isManagingTags) R.string.tags_done_managing else R.string.tags_manage))
                        }
                    }
                }
            }
        }

        OptionRow(icon = R.drawable.ic_circle, label = stringResource(R.string.task_color), error = null) {
            ColorSwatches(selected = form.color, onSelect = actions.onColorChange, enabled = enabled)
        }
    }
}

/** One option: its icon, which also names it, then its controls beside it. */
@Composable
private fun OptionRow(@DrawableRes icon: Int, label: String, error: String?, content: @Composable () -> Unit) {
    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.md)) {
        // Centred on the first line of controls, which are 48dp tall.
        Box(contentAlignment = Alignment.Center, modifier = Modifier.height(IconSize.touchTarget)) {
            Icon(
                painter = painterResource(icon),
                contentDescription = label,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(IconSize.md),
            )
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
            content()
            error?.let { ErrorMessage(lines = listOf(it)) }
        }
    }
}

/** No colour, then each Tag group colour, as round swatches with the picked one ringed. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ColorSwatches(selected: TagColor?, onSelect: (TagColor?) -> Unit, enabled: Boolean) {
    FlowRow(modifier = Modifier.testTag("task-colors")) {
        (listOf<TagColor?>(null) + TagColor.entries).forEach { color ->
            val label = stringResource(color?.labelRes() ?: R.string.task_color_none)
            val isSelected = color == selected
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(IconSize.touchTarget)
                    .selectable(selected = isSelected, enabled = enabled, role = Role.RadioButton, onClick = { onSelect(color) })
                    .semantics { contentDescription = label }
                    .testTag("task-color-${color?.wire ?: "none"}"),
            ) {
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .size(32.dp)
                        .border(
                            width = if (isSelected) 2.dp else 1.dp,
                            color = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant,
                            shape = CircleShape,
                        )
                        .padding(3.dp),
                ) {
                    if (color == null) {
                        Icon(painterResource(R.drawable.ic_ban), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    } else {
                        Surface(shape = CircleShape, color = color.tone().container, modifier = Modifier.fillMaxSize()) {}
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun DueDateDialog(initial: LocalDate, onPicked: (LocalDate) -> Unit, onDismiss: () -> Unit) {
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
internal fun DueTimeDialog(initial: LocalTime, onPicked: (LocalTime) -> Unit, onDismiss: () -> Unit, onRemove: (() -> Unit)? = null) {
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
        dismissButton = {
            Row {
                // Takes the time off the due, keeping its day.
                onRemove?.let {
                    TextButton(onClick = it, modifier = Modifier.testTag("task-due-time-remove")) { Text(stringResource(R.string.task_due_no_time)) }
                }
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
            }
        },
    )
}
