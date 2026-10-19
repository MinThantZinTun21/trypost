package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskDraft
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Clock
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.ZoneId
import java.time.temporal.ChronoUnit
import javax.inject.Inject

/** Where a server validation message shows on the editor. */
enum class TaskField { Title, Description, Priority, Due, TaskTags }

/** The editor's due choice: none, a whole day, or a day and a time. */
enum class DueKind { None, Date, DateTime }

/** The editor's fields, compared with what was loaded to tell an unsaved change. */
data class TaskForm(
    val title: String = "",
    val description: String = "",
    val priority: TaskPriority = TaskPriority.None,
    val due: TaskDue? = null,
    val taskTagIds: List<String> = emptyList(),
) {
    val dueKind: DueKind
        get() = when (due) {
            null -> DueKind.None
            is TaskDue.AllDay -> DueKind.Date
            is TaskDue.Timed -> DueKind.DateTime
        }

    fun toDraft(): TaskDraft = TaskDraft(
        title = title,
        description = description.takeIf { it.isNotBlank() },
        priority = priority,
        due = due,
        taskTagIds = taskTagIds,
    )
}

data class TaskEditorUiState(
    /** True for New task; false while editing a stored Task. */
    val isNew: Boolean,
    /** The stored Task being edited; null for a new one. */
    val task: Task? = null,
    val form: TaskForm = TaskForm(),
    /** The form as it was loaded (empty for a new Task). */
    val initialForm: TaskForm = TaskForm(),
    val isLoading: Boolean = false,
    val loadError: ApiError? = null,
    val isSaving: Boolean = false,
    /** A save error that no field shows: a lost connection, a 404, or a message without a known field. */
    val saveError: ApiError? = null,
    val fieldErrors: Map<TaskField, String> = emptyMap(),
    val isSaved: Boolean = false,
) {
    val isReady: Boolean get() = isNew || task != null

    /** True once a field differs from what was loaded; leaving then asks first. */
    val hasUnsavedChanges: Boolean get() = isReady && !isSaved && form != initialForm
}

/**
 * Creates a Task (no id) or edits one (an id). The due is kept in the
 * phone's timezone and sent with its UTC offset; the server's 422 messages
 * show under the field they are about.
 */
@HiltViewModel
class TaskEditorViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val tasksRepository: TasksRepository,
    private val clock: Clock,
) : ViewModel() {

    private val taskId: String? = savedStateHandle["id"]

    private val zone: ZoneId get() = clock.zone

    private val _state = MutableStateFlow(TaskEditorUiState(isNew = taskId == null))
    val state: StateFlow<TaskEditorUiState> = _state.asStateFlow()

    init {
        if (taskId != null) {
            load(taskId)
        }
    }

    fun retry() {
        taskId?.let(::load)
    }

    fun changeTitle(value: String) = changeForm(TaskField.Title) { it.copy(title = value) }

    fun changeDescription(value: String) = changeForm(TaskField.Description) { it.copy(description = value) }

    fun changePriority(value: TaskPriority) = changeForm(TaskField.Priority) { it.copy(priority = value) }

    /**
     * Switches between no due, a date and a date with a time, keeping the
     * day already picked. A new date starts today; a new time starts at the
     * next full hour.
     */
    fun changeDueKind(kind: DueKind) = changeForm(TaskField.Due) { form ->
        val date = form.due?.date ?: LocalDate.now(clock)
        val due = when (kind) {
            DueKind.None -> null
            DueKind.Date -> TaskDue.AllDay(date)
            DueKind.DateTime -> (form.due as? TaskDue.Timed) ?: TaskDue.Timed(LocalDateTime.of(date, nextFullHour()))
        }
        form.copy(due = due)
    }

    fun changeDueDate(date: LocalDate) = changeForm(TaskField.Due) { form ->
        form.copy(
            due = when (val due = form.due) {
                is TaskDue.Timed -> TaskDue.Timed(LocalDateTime.of(date, due.at.toLocalTime()))
                else -> TaskDue.AllDay(date)
            },
        )
    }

    fun changeDueTime(time: LocalTime) = changeForm(TaskField.Due) { form ->
        form.copy(due = TaskDue.Timed(LocalDateTime.of(form.due?.date ?: LocalDate.now(clock), time)))
    }

    fun toggleTaskTag(id: String) = changeForm(TaskField.TaskTags) { form ->
        form.copy(taskTagIds = if (id in form.taskTagIds) form.taskTagIds - id else form.taskTagIds + id)
    }

    /** Drops picked Task tags that no longer exist, e.g. just deleted in the tag picker. */
    fun keepOnlyKnownTaskTags(knownIds: Set<String>) {
        _state.update { state ->
            val kept = state.form.taskTagIds.filter { it in knownIds }
            if (kept.size == state.form.taskTagIds.size) {
                state
            } else {
                state.copy(
                    form = state.form.copy(taskTagIds = kept),
                    initialForm = state.initialForm.copy(taskTagIds = state.initialForm.taskTagIds.filter { it in knownIds }),
                )
            }
        }
    }

    fun save() {
        val current = _state.value
        if (current.isSaving || !current.isReady) {
            return
        }

        _state.update { it.copy(isSaving = true, saveError = null, fieldErrors = emptyMap()) }

        viewModelScope.launch {
            val draft = current.form.toDraft()
            val result = if (taskId == null) tasksRepository.create(draft, zone) else tasksRepository.update(taskId, draft, zone)

            when (result) {
                is ApiResult.Success -> _state.update { it.copy(task = result.value, isSaving = false, isSaved = true) }
                is ApiResult.Failure -> _state.update { it.copy(isSaving = false).withSaveError(result.error) }
            }
        }
    }

    private fun changeForm(field: TaskField, transform: (TaskForm) -> TaskForm) {
        _state.update { it.copy(form = transform(it.form), fieldErrors = it.fieldErrors - field) }
    }

    private fun nextFullHour(): LocalTime = LocalTime.now(clock).truncatedTo(ChronoUnit.HOURS).plusHours(1)

    private fun load(id: String) {
        _state.update { it.copy(isLoading = true, loadError = null) }

        viewModelScope.launch {
            when (val result = tasksRepository.task(id)) {
                is ApiResult.Success -> {
                    val form = result.value.toForm()
                    _state.update { it.copy(task = result.value, form = form, initialForm = form, isLoading = false) }
                }
                is ApiResult.Failure -> _state.update { it.copy(isLoading = false, loadError = result.error) }
            }
        }
    }

    private fun Task.toForm(): TaskForm = TaskForm(
        title = title,
        description = description.orEmpty(),
        priority = priority,
        due = due(zone),
        taskTagIds = taskTags.map { it.id },
    )

    private fun TaskEditorUiState.withSaveError(error: ApiError): TaskEditorUiState {
        if (error !is ApiError.Validation) {
            return copy(saveError = error)
        }

        val fields = mutableMapOf<TaskField, String>()
        var hasUnplaced = false
        error.fields.forEach { (key, messages) ->
            val field = taskFieldForErrorKey(key)
            val message = messages.firstOrNull() ?: return@forEach
            if (field == null) {
                hasUnplaced = true
            } else {
                fields.putIfAbsent(field, message)
            }
        }

        return copy(fieldErrors = fields, saveError = error.takeIf { hasUnplaced || fields.isEmpty() })
    }
}

/** The editor field a Task's validation key is about: `task_tags.2` is the Task tags, `due_all_day` the due. */
fun taskFieldForErrorKey(key: String): TaskField? = when (key.substringBefore('.')) {
    "title" -> TaskField.Title
    "description" -> TaskField.Description
    "priority" -> TaskField.Priority
    "due_at", "due_all_day" -> TaskField.Due
    "task_tags" -> TaskField.TaskTags
    else -> null
}
