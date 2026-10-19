package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.InlineStyle
import com.minthantzintun.trypost.core.model.TagColor
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
    val color: TagColor? = null,
    val isPinned: Boolean = false,
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
        color = color,
        isPinned = isPinned,
    )
}

data class TaskEditorUiState(
    /** True for New task; false while editing a stored Task. */
    val isNew: Boolean,
    /** The stored Task being edited; null for a new one. */
    val task: Task? = null,
    /** The fields as saved; [TaskForm.description] follows [body]. */
    val form: TaskForm = TaskForm(),
    /** The form as it was loaded (empty for a new Task). */
    val initialForm: TaskForm = TaskForm(),
    /** The body as the block editor shows it. */
    val body: BodyEditorState = BodyEditorState.fromMarkdown(null),
    val isLoading: Boolean = false,
    val loadError: ApiError? = null,
    val isSaving: Boolean = false,
    /** A save error that no field shows: a lost connection, a 404, or a message without a known field. */
    val saveError: ApiError? = null,
    val fieldErrors: Map<TaskField, String> = emptyMap(),
    val isDeleting: Boolean = false,
    /** The editor is done: saved, discarded, or left with nothing to save. */
    val isClosed: Boolean = false,
    /** The Task was deleted, so the screens behind it go too. */
    val isDeleted: Boolean = false,
) {
    val isReady: Boolean get() = isNew || task != null

    /** True once a field differs from what was loaded. */
    val hasUnsavedChanges: Boolean get() = isReady && !isClosed && form != initialForm

    /** A Task needs a title or a body; a new one without either is not created. */
    val isEmpty: Boolean get() = form.title.isBlank() && body.isBlank
}

/**
 * Creates a Task (no id) or edits one (an id), Keep-style: Back saves. The
 * body is a block editor written as Markdown; the due is kept in the
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

    private val _state = MutableStateFlow(
        TaskEditorUiState(
            isNew = taskId == null,
            body = when {
                taskId != null -> BodyEditorState.fromMarkdown(null)
                savedStateHandle.get<Boolean>("startsAsChecklist") == true -> BodyEditorState.checklist()
                // Take a note: the keyboard opens on the body, as in Keep.
                else -> BodyEditorState.fromMarkdown(null).copy(selection = EditorSelection(0, 0), focusRequest = 1)
            },
        ),
    )
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

    /** A body field now reads [text] with the cursor or selection at [start]..[end]. */
    fun changeBlockText(blockId: Long, text: String, start: Int, end: Int = start) = changeBody { it.changeText(blockId, text, start, end) }

    /** The cursor moved inside a body field without a change to its text. */
    fun selectInBody(blockId: Long, start: Int, end: Int = start) = changeBody { it.select(blockId, start, end) }

    fun backspaceAtBlockStart(blockId: Long) = changeBody { it.backspaceAtStart(blockId) }

    fun toggleBlockKind(kind: BlockKind) = changeBody { it.toggleKind(kind) }

    fun toggleStyle(style: InlineStyle) = changeBody { it.toggleStyle(style) }

    fun toggleChecked(blockId: Long) = changeBody { it.toggleChecked(blockId) }

    /** The card's colour, or none. */
    fun changeColor(color: TagColor?) = changeForm(null) { it.copy(color = color) }

    fun togglePin() = changeForm(null) { it.copy(isPinned = !it.isPinned) }

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

    /**
     * Back: saves a change and closes once the server takes it. An untouched
     * Task, or a new one with no title and no body, closes without a
     * request; a refused save stays open with the reason.
     */
    fun leave() {
        val current = _state.value
        if (current.isSaving || current.isDeleting) {
            return
        }

        when {
            !current.isReady || !current.hasUnsavedChanges || (current.isNew && current.isEmpty) -> _state.update { it.copy(isClosed = true) }
            else -> save()
        }
    }

    /** Leaves without saving. */
    fun discard() {
        _state.update { it.copy(isClosed = true) }
    }

    /** Deletes the stored Task for good; nothing for a new one. */
    fun delete() {
        val current = _state.value
        if (taskId == null || current.isSaving || current.isDeleting) {
            return
        }

        _state.update { it.copy(isDeleting = true, saveError = null) }

        viewModelScope.launch {
            when (val result = tasksRepository.delete(taskId)) {
                is ApiResult.Success -> _state.update { it.copy(isDeleting = false, isDeleted = true, isClosed = true) }
                is ApiResult.Failure -> _state.update { it.copy(isDeleting = false, saveError = result.error) }
            }
        }
    }

    private fun save() {
        val current = _state.value

        _state.update { it.copy(isSaving = true, saveError = null, fieldErrors = emptyMap()) }

        viewModelScope.launch {
            val draft = current.form.toDraft()
            val result = if (taskId == null) tasksRepository.create(draft, zone) else tasksRepository.update(taskId, draft, zone)

            when (result) {
                is ApiResult.Success -> _state.update { it.copy(task = result.value, isSaving = false, isClosed = true) }
                is ApiResult.Failure -> _state.update { it.copy(isSaving = false).withSaveError(result.error) }
            }
        }
    }

    private fun changeForm(field: TaskField?, transform: (TaskForm) -> TaskForm) {
        _state.update { it.copy(form = transform(it.form), fieldErrors = if (field == null) it.fieldErrors else it.fieldErrors - field) }
    }

    private fun changeBody(transform: (BodyEditorState) -> BodyEditorState) {
        _state.update { state ->
            val body = transform(state.body)
            val description = body.toMarkdown()
            if (description == state.form.description) {
                state.copy(body = body)
            } else {
                state.copy(body = body, form = state.form.copy(description = description), fieldErrors = state.fieldErrors - TaskField.Description)
            }
        }
    }

    private fun nextFullHour(): LocalTime = LocalTime.now(clock).truncatedTo(ChronoUnit.HOURS).plusHours(1)

    private fun load(id: String) {
        _state.update { it.copy(isLoading = true, loadError = null) }

        viewModelScope.launch {
            when (val result = tasksRepository.task(id)) {
                is ApiResult.Success -> {
                    // The body is written back as the editor writes it, so a tap alone is not a change.
                    val body = BodyEditorState.fromMarkdown(result.value.description)
                    val form = result.value.toForm().copy(description = body.toMarkdown())
                    _state.update { it.copy(task = result.value, form = form, initialForm = form, body = body, isLoading = false) }
                }
                is ApiResult.Failure -> _state.update { it.copy(isLoading = false, loadError = result.error) }
            }
        }
    }

    private fun Task.toForm(): TaskForm = TaskForm(
        title = title.orEmpty(),
        description = description.orEmpty(),
        priority = priority,
        due = due(zone),
        taskTagIds = taskTags.map { it.id },
        color = color,
        isPinned = isPinned,
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
