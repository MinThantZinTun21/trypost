package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.TaskChange
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TagGroup
import com.minthantzintun.trypost.core.model.TaskTag
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** The dialog the tag picker has open: a form or a delete confirm. */
sealed interface TagDialog {
    data object NewTagGroup : TagDialog

    data class EditTagGroup(val tagGroup: TagGroup) : TagDialog

    data class DeleteTagGroup(val tagGroup: TagGroup) : TagDialog

    data class NewTaskTag(val tagGroup: TagGroup) : TagDialog

    data class EditTaskTag(val taskTag: TaskTag) : TagDialog

    data class DeleteTaskTag(val taskTag: TaskTag) : TagDialog
}

data class TaskTagsUiState(
    /** Null until the first load succeeds, so a failed load never reads as "no Task tags". */
    val tagGroups: List<TagGroup>? = null,
    val isLoading: Boolean = false,
    val loadError: ApiError? = null,
    val dialog: TagDialog? = null,
    val isSaving: Boolean = false,
    /** The server's message about the dialog's name or colour, shown under that field. */
    val nameError: String? = null,
    val colorError: String? = null,
    /** A refusal no field shows: a lost connection, a 404, a message about something else. */
    val dialogError: ApiError? = null,
)

/**
 * Tag groups and their Task tags for the tag picker and the filter sheet:
 * lists them and creates, renames, recolours and deletes them on the
 * server, loading the list again after each change.
 */
@HiltViewModel
class TaskTagsViewModel @Inject constructor(
    private val tasksRepository: TasksRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(TaskTagsUiState())
    val state: StateFlow<TaskTagsUiState> = _state.asStateFlow()

    private var loadJob: Job? = null

    init {
        load()

        viewModelScope.launch {
            tasksRepository.changes.collect { change ->
                if (change == TaskChange.TaskTagsChanged) {
                    load()
                }
            }
        }
    }

    /** Loads the Tag groups, keeping the last list while it does. */
    fun load() {
        loadJob?.cancel()
        _state.update { it.copy(isLoading = true, loadError = null) }

        loadJob = viewModelScope.launch {
            when (val result = tasksRepository.tagGroups()) {
                is ApiResult.Success -> _state.update { it.copy(tagGroups = result.value, isLoading = false) }
                is ApiResult.Failure -> _state.update { it.copy(isLoading = false, loadError = result.error) }
            }
        }
    }

    fun openDialog(dialog: TagDialog) {
        _state.update { it.copy(dialog = dialog, nameError = null, colorError = null, dialogError = null) }
    }

    fun closeDialog() {
        if (_state.value.isSaving) {
            return
        }

        _state.update { it.copy(dialog = null, nameError = null, colorError = null, dialogError = null) }
    }

    /** Creates or updates the open dialog's Tag group. */
    fun saveTagGroup(name: String, color: TagColor) {
        when (val dialog = _state.value.dialog) {
            TagDialog.NewTagGroup -> run { tasksRepository.createTagGroup(name, color) }
            is TagDialog.EditTagGroup -> run { tasksRepository.updateTagGroup(dialog.tagGroup.id, name, color) }
            else -> Unit
        }
    }

    /** Creates or renames the open dialog's Task tag. */
    fun saveTaskTag(name: String) {
        when (val dialog = _state.value.dialog) {
            is TagDialog.NewTaskTag -> run { tasksRepository.createTaskTag(dialog.tagGroup.id, name) }
            is TagDialog.EditTaskTag -> run { tasksRepository.renameTaskTag(dialog.taskTag.id, name) }
            else -> Unit
        }
    }

    /** Deletes what the open confirm is about; its Task tags leave every Task. */
    fun confirmDelete() {
        when (val dialog = _state.value.dialog) {
            is TagDialog.DeleteTagGroup -> run { tasksRepository.deleteTagGroup(dialog.tagGroup.id) }
            is TagDialog.DeleteTaskTag -> run { tasksRepository.deleteTaskTag(dialog.taskTag.id) }
            else -> Unit
        }
    }

    /** Runs one change; on success the dialog closes (the repository's change reloads the list). */
    private fun run(action: suspend () -> ApiResult<*>) {
        if (_state.value.isSaving) {
            return
        }

        _state.update { it.copy(isSaving = true, nameError = null, colorError = null, dialogError = null) }

        viewModelScope.launch {
            when (val result = action()) {
                is ApiResult.Success -> _state.update { it.copy(isSaving = false, dialog = null) }
                is ApiResult.Failure -> _state.update { it.copy(isSaving = false).withError(result.error) }
            }
        }
    }

    private fun TaskTagsUiState.withError(error: ApiError): TaskTagsUiState {
        if (error !is ApiError.Validation) {
            return copy(dialogError = error)
        }

        val nameError = error.fields["name"]?.firstOrNull()
        val colorError = error.fields["color"]?.firstOrNull()

        return copy(
            nameError = nameError,
            colorError = colorError,
            dialogError = error.takeIf { nameError == null && colorError == null },
        )
    }
}
