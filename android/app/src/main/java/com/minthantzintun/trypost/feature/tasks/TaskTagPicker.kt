package com.minthantzintun.trypost.feature.tasks

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.MenuDefaults
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
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
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.ErrorMessage
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TagGroup
import com.minthantzintun.trypost.core.model.TaskTag
import com.minthantzintun.trypost.core.ui.message

/**
 * The Tag groups with their Task tags as chips to pick. With [onOpenDialog]
 * it also manages them: each group's menu edits, deletes or adds a Task tag,
 * and in [isManaging] a tap on a Task tag opens its own menu instead of
 * picking it.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun TagGroupsChooser(
    tagGroups: List<TagGroup>,
    selectedIds: Collection<String>,
    onToggle: (String) -> Unit,
    modifier: Modifier = Modifier,
    isManaging: Boolean = false,
    onOpenDialog: ((TagDialog) -> Unit)? = null,
) {
    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(Spacing.lg)) {
        tagGroups.forEach { group ->
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs), modifier = Modifier.testTag("tag-group-${group.id}")) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    TagColorDot(group.color)
                    Text(
                        text = group.name,
                        style = MaterialTheme.typography.titleSmall,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f).semantics { heading() },
                    )
                    if (onOpenDialog != null) {
                        TagGroupMenu(group, onOpenDialog)
                    }
                }
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                    verticalArrangement = Arrangement.spacedBy(Spacing.xs),
                ) {
                    group.taskTags.forEach { taskTag ->
                        if (isManaging && onOpenDialog != null) {
                            ManagedTaskTagChip(taskTag, group.color, onOpenDialog)
                        } else {
                            TaskTagChip(taskTag, group.color, isSelected = taskTag.id in selectedIds, onClick = { onToggle(taskTag.id) })
                        }
                    }
                    if (onOpenDialog != null) {
                        AssistChip(
                            onClick = { onOpenDialog(TagDialog.NewTaskTag(group)) },
                            label = { Text(stringResource(R.string.tags_new_tag)) },
                            leadingIcon = { Icon(painterResource(R.drawable.ic_plus), contentDescription = null, modifier = Modifier.size(IconSize.sm)) },
                            modifier = Modifier.testTag("new-task-tag-${group.id}"),
                        )
                    }
                    if (group.taskTags.isEmpty() && onOpenDialog == null) {
                        Text(
                            text = stringResource(R.string.tags_group_empty),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun TaskTagChip(taskTag: TaskTag, color: TagColor, isSelected: Boolean, onClick: () -> Unit) {
    val tone = color.tone()

    FilterChip(
        selected = isSelected,
        onClick = onClick,
        label = { Text(taskTag.name, maxLines = 1, overflow = TextOverflow.Ellipsis) },
        leadingIcon = if (isSelected) {
            { Icon(painterResource(R.drawable.ic_check), contentDescription = null, modifier = Modifier.size(IconSize.sm)) }
        } else {
            null
        },
        colors = FilterChipDefaults.filterChipColors(
            selectedContainerColor = tone.container,
            selectedLabelColor = tone.content,
            selectedLeadingIconColor = tone.content,
        ),
        modifier = Modifier.testTag("task-tag-${taskTag.id}"),
    )
}

/** In Manage, a Task tag opens Rename and Delete. */
@Composable
private fun ManagedTaskTagChip(taskTag: TaskTag, color: TagColor, onOpenDialog: (TagDialog) -> Unit) {
    var isMenuOpen by remember { mutableStateOf(false) }

    Box {
        AssistChip(
            onClick = { isMenuOpen = true },
            label = { Text(taskTag.name, maxLines = 1, overflow = TextOverflow.Ellipsis) },
            leadingIcon = { TagColorDot(color, size = 8.dp) },
            trailingIcon = { Icon(painterResource(R.drawable.ic_edit), contentDescription = null, modifier = Modifier.size(IconSize.xs)) },
            modifier = Modifier.testTag("manage-task-tag-${taskTag.id}"),
        )
        DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
            DropdownMenuItem(
                text = { Text(stringResource(R.string.tags_rename)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_edit), contentDescription = null) },
                onClick = {
                    isMenuOpen = false
                    onOpenDialog(TagDialog.EditTaskTag(taskTag))
                },
            )
            DropdownMenuItem(
                text = { Text(stringResource(R.string.delete)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_delete), contentDescription = null) },
                colors = MenuDefaults.itemColors(textColor = MaterialTheme.colorScheme.error, leadingIconColor = MaterialTheme.colorScheme.error),
                onClick = {
                    isMenuOpen = false
                    onOpenDialog(TagDialog.DeleteTaskTag(taskTag))
                },
            )
        }
    }
}

@Composable
private fun TagGroupMenu(group: TagGroup, onOpenDialog: (TagDialog) -> Unit) {
    var isMenuOpen by remember { mutableStateOf(false) }

    Box {
        ActionIconButton(
            icon = R.drawable.ic_more,
            label = stringResource(R.string.tags_group_options, group.name),
            onClick = { isMenuOpen = true },
            modifier = Modifier.testTag("tag-group-menu-${group.id}"),
        )
        DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
            DropdownMenuItem(
                text = { Text(stringResource(R.string.tags_new_tag)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_plus), contentDescription = null) },
                onClick = {
                    isMenuOpen = false
                    onOpenDialog(TagDialog.NewTaskTag(group))
                },
            )
            DropdownMenuItem(
                text = { Text(stringResource(R.string.tags_edit_group)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_edit), contentDescription = null) },
                onClick = {
                    isMenuOpen = false
                    onOpenDialog(TagDialog.EditTagGroup(group))
                },
            )
            DropdownMenuItem(
                text = { Text(stringResource(R.string.tags_delete_group)) },
                leadingIcon = { Icon(painterResource(R.drawable.ic_delete), contentDescription = null) },
                colors = MenuDefaults.itemColors(textColor = MaterialTheme.colorScheme.error, leadingIconColor = MaterialTheme.colorScheme.error),
                onClick = {
                    isMenuOpen = false
                    onOpenDialog(TagDialog.DeleteTagGroup(group))
                },
                modifier = Modifier.testTag("delete-tag-group-${group.id}"),
            )
        }
    }
}

/**
 * The editor's tag picker: pick the Task's Task tags, and create, rename,
 * recolour and delete Tag groups and Task tags without leaving the editor.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TaskTagPickerSheet(
    state: TaskTagsUiState,
    selectedIds: Collection<String>,
    onToggle: (String) -> Unit,
    onOpenDialog: (TagDialog) -> Unit,
    onRetry: () -> Unit,
    onDismiss: () -> Unit,
) {
    var isManaging by rememberSaveable { mutableStateOf(false) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = MaterialTheme.colorScheme.surfaceContainerLow,
    ) {
        Column(modifier = Modifier.fillMaxWidth().padding(bottom = Spacing.lg).testTag("tag-picker")) {
            Row(
                modifier = Modifier.fillMaxWidth().padding(start = Spacing.xl, end = Spacing.md),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = stringResource(R.string.tags_title),
                    style = MaterialTheme.typography.titleLarge,
                    modifier = Modifier.weight(1f).semantics { heading() },
                )
                if (!state.tagGroups.isNullOrEmpty()) {
                    TextButton(onClick = { isManaging = !isManaging }, modifier = Modifier.testTag("tags-manage")) {
                        Text(stringResource(if (isManaging) R.string.tags_done_managing else R.string.tags_manage))
                    }
                }
                Button(
                    onClick = onDismiss,
                    modifier = Modifier.testTag("tag-picker-done"),
                ) { Text(stringResource(R.string.done)) }
            }

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = Spacing.xl, vertical = Spacing.md),
                verticalArrangement = Arrangement.spacedBy(Spacing.lg),
            ) {
                if (isManaging) {
                    Text(
                        text = stringResource(R.string.tags_manage_hint),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                TagGroupsContent(
                    state = state,
                    selectedIds = selectedIds,
                    onToggle = onToggle,
                    onRetry = onRetry,
                    isManaging = isManaging,
                    onOpenDialog = onOpenDialog,
                )
                TextButton(
                    onClick = { onOpenDialog(TagDialog.NewTagGroup) },
                    contentPadding = ButtonDefaults.TextButtonWithIconContentPadding,
                    modifier = Modifier.testTag("new-tag-group"),
                ) {
                    Icon(painterResource(R.drawable.ic_plus), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    Text(stringResource(R.string.tags_new_group), modifier = Modifier.padding(start = Spacing.xs))
                }
            }
        }
    }
}

/** The Tag groups, or their loading, error or empty state. */
@Composable
fun TagGroupsContent(
    state: TaskTagsUiState,
    selectedIds: Collection<String>,
    onToggle: (String) -> Unit,
    onRetry: () -> Unit,
    isManaging: Boolean = false,
    onOpenDialog: ((TagDialog) -> Unit)? = null,
) {
    val tagGroups = state.tagGroups
    val loadError = state.loadError

    when {
        tagGroups == null && loadError != null -> StateMessage(
            icon = R.drawable.ic_cloud_off,
            title = stringResource(R.string.tags_error_title),
            body = loadError.message(),
            actionLabel = stringResource(R.string.retry),
            onAction = onRetry,
            isError = true,
        )
        tagGroups == null -> Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            repeat(2) {
                Skeleton(Modifier.width(120.dp).height(16.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    repeat(3) { Skeleton(Modifier.width(72.dp).height(32.dp), shape = MaterialTheme.shapes.small) }
                }
            }
        }
        tagGroups.isEmpty() -> Text(
            text = stringResource(R.string.tags_empty),
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.testTag("tags-empty"),
        )
        else -> TagGroupsChooser(
            tagGroups = tagGroups,
            selectedIds = selectedIds,
            onToggle = onToggle,
            isManaging = isManaging,
            onOpenDialog = onOpenDialog,
        )
    }
}

/** The tag picker's open dialog: a Tag group or Task tag form, or a delete confirm. */
@Composable
fun TagDialogs(
    state: TaskTagsUiState,
    onSaveTagGroup: (name: String, color: TagColor) -> Unit,
    onSaveTaskTag: (name: String) -> Unit,
    onConfirmDelete: () -> Unit,
    onDismiss: () -> Unit,
) {
    when (val dialog = state.dialog) {
        null -> Unit
        TagDialog.NewTagGroup -> TagGroupDialog(null, state, onSaveTagGroup, onDismiss)
        is TagDialog.EditTagGroup -> TagGroupDialog(dialog.tagGroup, state, onSaveTagGroup, onDismiss)
        is TagDialog.NewTaskTag -> TaskTagDialog(
            title = stringResource(R.string.tags_new_tag_in, dialog.tagGroup.name),
            initialName = "",
            state = state,
            onSave = onSaveTaskTag,
            onDismiss = onDismiss,
        )
        is TagDialog.EditTaskTag -> TaskTagDialog(
            title = stringResource(R.string.tags_rename_tag),
            initialName = dialog.taskTag.name,
            state = state,
            onSave = onSaveTaskTag,
            onDismiss = onDismiss,
        )
        is TagDialog.DeleteTagGroup -> DeleteDialog(
            title = stringResource(R.string.tags_delete_group_title, dialog.tagGroup.name),
            body = stringResource(R.string.tags_delete_group_body),
            state = state,
            onConfirm = onConfirmDelete,
            onDismiss = onDismiss,
        )
        is TagDialog.DeleteTaskTag -> DeleteDialog(
            title = stringResource(R.string.tags_delete_tag_title, dialog.taskTag.name),
            body = stringResource(R.string.tags_delete_tag_body),
            state = state,
            onConfirm = onConfirmDelete,
            onDismiss = onDismiss,
        )
    }
}

@Composable
private fun TagGroupDialog(
    tagGroup: TagGroup?,
    state: TaskTagsUiState,
    onSave: (name: String, color: TagColor) -> Unit,
    onDismiss: () -> Unit,
) {
    var name by rememberSaveable { mutableStateOf(tagGroup?.name.orEmpty()) }
    var color by rememberSaveable { mutableStateOf(tagGroup?.color ?: TagColor.Blue) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(if (tagGroup == null) R.string.tags_new_group else R.string.tags_edit_group)) },
        text = {
            Column(modifier = Modifier.imePadding(), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
                FormField(
                    value = name,
                    onValueChange = { name = it },
                    label = stringResource(R.string.tags_group_name),
                    enabled = !state.isSaving,
                    isError = state.nameError != null,
                    supportingText = state.nameError ?: stringResource(R.string.tags_group_name_hint),
                    modifier = Modifier.testTag("tag-group-name"),
                )
                Text(stringResource(R.string.tags_group_color), style = MaterialTheme.typography.labelLarge)
                ColorSwatches(selected = color, onSelect = { color = it })
                state.colorError?.let { ErrorMessage(lines = listOf(it)) }
                state.dialogError?.let { ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("tag-dialog-error")) }
            }
        },
        confirmButton = {
            Button(
                onClick = { onSave(name, color) },
                enabled = !state.isSaving,
                contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                modifier = Modifier.testTag("tag-group-save"),
            ) { ButtonLabel(icon = R.drawable.ic_check, label = stringResource(R.string.save), isBusy = state.isSaving) }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !state.isSaving) { Text(stringResource(R.string.cancel)) } },
    )
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ColorSwatches(selected: TagColor, onSelect: (TagColor) -> Unit) {
    FlowRow(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        TagColor.entries.forEach { color ->
            val tone = color.tone()
            val isSelected = color == selected
            val label = stringResource(color.labelRes())

            Box(
                modifier = Modifier
                    .size(IconSize.touchTarget)
                    .clickable(role = Role.RadioButton) { onSelect(color) }
                    .semantics {
                        contentDescription = label
                        this.selected = isSelected
                    }
                    .testTag("tag-color-${color.wire}"),
                contentAlignment = Alignment.Center,
            ) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .background(tone.container, CircleShape)
                        .border(
                            if (isSelected) BorderStroke(3.dp, MaterialTheme.colorScheme.onSurface) else BorderStroke(2.dp, tone.content),
                            CircleShape,
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    if (isSelected) {
                        Icon(painterResource(R.drawable.ic_check), contentDescription = null, tint = tone.content, modifier = Modifier.size(IconSize.sm))
                    }
                }
            }
        }
    }
}

@Composable
private fun TaskTagDialog(
    title: String,
    initialName: String,
    state: TaskTagsUiState,
    onSave: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    var name by rememberSaveable { mutableStateOf(initialName) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
                FormField(
                    value = name,
                    onValueChange = { name = it },
                    label = stringResource(R.string.tags_tag_name),
                    enabled = !state.isSaving,
                    isError = state.nameError != null,
                    supportingText = state.nameError,
                    modifier = Modifier.testTag("task-tag-name"),
                )
                state.dialogError?.let { ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("tag-dialog-error")) }
            }
        },
        confirmButton = {
            Button(
                onClick = { onSave(name) },
                enabled = !state.isSaving,
                contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                modifier = Modifier.testTag("task-tag-save"),
            ) { ButtonLabel(icon = R.drawable.ic_check, label = stringResource(R.string.save), isBusy = state.isSaving) }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !state.isSaving) { Text(stringResource(R.string.cancel)) } },
    )
}

/** A red delete confirm that stays open with the reason when the server refuses. */
@Composable
private fun DeleteDialog(title: String, body: String, state: TaskTagsUiState, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(painterResource(R.drawable.ic_delete), contentDescription = null) },
        title = { Text(title) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
                Text(body)
                state.dialogError?.let { ErrorMessage(lines = listOf(it.message()), modifier = Modifier.testTag("tag-dialog-error")) }
            }
        },
        confirmButton = {
            Button(
                onClick = onConfirm,
                enabled = !state.isSaving,
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.error,
                    contentColor = MaterialTheme.colorScheme.onError,
                ),
                contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                modifier = Modifier.testTag("tag-delete-confirm"),
            ) { ButtonLabel(icon = R.drawable.ic_delete, label = stringResource(R.string.delete), isBusy = state.isSaving) }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !state.isSaving) { Text(stringResource(R.string.cancel)) } },
    )
}
