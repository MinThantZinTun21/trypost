package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.AttachmentUpload
import com.minthantzintun.trypost.core.data.TaskAttachmentUploads
import com.minthantzintun.trypost.core.data.TaskAttachmentsRepository
import com.minthantzintun.trypost.core.data.TaskChange
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.data.UploadSource
import com.minthantzintun.trypost.core.model.AttachmentType
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskBody
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.model.TimerSession
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.timer.TimerTracker
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.time.Clock
import javax.inject.Inject

data class TaskDetailUiState(
    val task: Task? = null,
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val error: ApiError? = null,
    /** The status the Owner just asked for, while the server saves it. */
    val savingStatus: TaskStatus? = null,
    val isDeleting: Boolean = false,
    val actionError: ApiError? = null,
    val isDeleted: Boolean = false,
    /** This Task's running timer, if the one running is on it. */
    val runningTimer: TimerSession? = null,
    /** Another Task's running timer, which a start here would be refused for. */
    val isTimerElsewhere: Boolean = false,
    val isStartingTimer: Boolean = false,
    val isStoppingTimer: Boolean = false,
    val pomodoroFocusMinutes: Int = SettingsStore.DEFAULT_FOCUS_MINUTES,
    val isSavingReminders: Boolean = false,
    val uploads: List<AttachmentUpload> = emptyList(),
    val deletingAttachmentId: String? = null,
)

/**
 * One Task: its body's checkboxes, Done or Reopen, To do or In progress,
 * Delete, and (spec #130) its reminders, timer and attachments.
 */
@HiltViewModel
class TaskDetailViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val tasksRepository: TasksRepository,
    private val clock: Clock,
    private val timerTracker: TimerTracker,
    private val attachmentUploads: TaskAttachmentUploads,
    private val attachmentsRepository: TaskAttachmentsRepository,
    settingsStore: SettingsStore,
) : ViewModel() {

    private val taskId: String = checkNotNull(savedStateHandle["id"]) { "TaskDetailDestination needs an id" }

    private val _state = MutableStateFlow(TaskDetailUiState())
    val state: StateFlow<TaskDetailUiState> = _state.asStateFlow()

    private var loadJob: Job? = null

    /** Ticks save one at a time, oldest first; each save carries every tick made so far. */
    private val tickLock = Mutex()
    private var pendingTicks = 0

    /** The Task as the server last confirmed it, which a refused tick rolls back to. */
    private var confirmedTask: Task? = null

    init {
        load(isRefresh = false)

        viewModelScope.launch {
            tasksRepository.changes.collect { change ->
                when (change) {
                    // While ticks save, the screen already shows them; an older save would undo the newest.
                    is TaskChange.Saved -> if (change.task.id == taskId && pendingTicks == 0) {
                        _state.update { it.copy(task = change.task, error = null) }
                    }
                    // A renamed or deleted Task tag reads differently on this Task.
                    TaskChange.TaskTagsChanged -> load(isRefresh = true)
                    // A start or stop changes the time tracked and the sessions.
                    is TaskChange.TimerChanged -> if (change.taskId == taskId) {
                        load(isRefresh = true)
                    }
                    is TaskChange.Deleted -> Unit
                }
            }
        }
        viewModelScope.launch {
            timerTracker.running.collect { running ->
                _state.update {
                    it.copy(
                        runningTimer = running?.takeIf { session -> session.taskId == taskId },
                        isTimerElsewhere = running != null && running.taskId != taskId,
                    )
                }
            }
        }
        viewModelScope.launch {
            attachmentUploads.uploads.collect { uploads ->
                _state.update { state -> state.copy(uploads = uploads.filter { it.taskId == taskId }) }
            }
        }
        viewModelScope.launch {
            settingsStore.pomodoroFocusMinutes.collect { minutes -> _state.update { it.copy(pomodoroFocusMinutes = minutes) } }
        }
    }

    fun refresh() {
        load(isRefresh = true)
    }

    fun retry() {
        load(isRefresh = false)
    }

    /** Done, Reopen (To do), or In progress; Done sets the completed time on the server. */
    fun setStatus(status: TaskStatus) {
        val current = _state.value
        if (current.savingStatus != null || current.isDeleting || pendingTicks > 0 || current.task?.status == status) {
            return
        }

        _state.update { it.copy(savingStatus = status, actionError = null) }

        viewModelScope.launch {
            when (val result = tasksRepository.setStatus(taskId, status)) {
                is ApiResult.Success -> _state.update { it.copy(task = result.value, savingStatus = null) }
                is ApiResult.Failure -> _state.update { it.copy(savingStatus = null, actionError = result.error) }
            }
        }
    }

    /**
     * Ticks or unticks the body's checkbox number [index] (counted from 0)
     * at once, then saves the whole Task. A refusal puts back the body the
     * server last saved and shows why.
     */
    fun toggleCheckbox(index: Int) {
        val current = _state.value
        val task = current.task ?: return
        val description = task.description ?: return
        if (current.savingStatus != null || current.isDeleting) {
            return
        }

        val ticked = TaskBody.toggleCheckbox(description, index)
        if (ticked == description) {
            return
        }

        if (pendingTicks == 0) {
            confirmedTask = task
        }
        pendingTicks++
        _state.update { it.copy(task = task.copy(description = ticked), actionError = null) }

        viewModelScope.launch {
            tickLock.withLock {
                val latest = _state.value.task
                val result = latest?.let { tasksRepository.update(taskId, it.toDraft(clock.zone), clock.zone) }
                pendingTicks--

                when (result) {
                    null -> Unit
                    is ApiResult.Success -> {
                        confirmedTask = result.value
                        if (pendingTicks == 0) {
                            _state.update { it.copy(task = result.value) }
                        }
                    }
                    is ApiResult.Failure -> _state.update { it.copy(task = confirmedTask ?: it.task, actionError = result.error) }
                }
            }
        }
    }

    /** Deletes the Task for good; the screen closes once the server confirms. */
    fun delete() {
        if (_state.value.isDeleting || _state.value.savingStatus != null) {
            return
        }

        _state.update { it.copy(isDeleting = true, actionError = null) }

        viewModelScope.launch {
            when (val result = tasksRepository.delete(taskId)) {
                is ApiResult.Success -> _state.update { it.copy(isDeleting = false, isDeleted = true) }
                is ApiResult.Failure -> _state.update { it.copy(isDeleting = false, actionError = result.error) }
            }
        }
    }

    /**
     * Gives the Task [reminders] as its whole set, keeping every other field
     * as saved. The server keeps a dismissed reminder dismissed while it
     * stays in the set.
     */
    fun saveReminders(reminders: List<ReminderDraft>) {
        val current = _state.value
        val task = current.task ?: return
        if (current.isSavingReminders || current.isDeleting || pendingTicks > 0) {
            return
        }

        _state.update { it.copy(isSavingReminders = true, actionError = null) }

        viewModelScope.launch {
            val draft = task.toDraft(clock.zone).copy(reminders = reminders)
            when (val result = tasksRepository.update(taskId, draft, clock.zone)) {
                is ApiResult.Success -> _state.update { it.copy(task = result.value, isSavingReminders = false) }
                is ApiResult.Failure -> _state.update { it.copy(isSavingReminders = false, actionError = result.error) }
            }
        }
    }

    /** Starts a Pomodoro of the length in Settings, or a free timer; one timer runs at a time. */
    fun startTimer(mode: TimerMode) {
        val current = _state.value
        if (current.isStartingTimer || current.runningTimer != null || current.task == null) {
            return
        }

        _state.update { it.copy(isStartingTimer = true, actionError = null) }

        viewModelScope.launch {
            val plannedSeconds = if (mode == TimerMode.Pomodoro) current.pomodoroFocusMinutes * 60L else null
            val result = tasksRepository.startTimer(taskId, mode, plannedSeconds)
            _state.update { it.copy(isStartingTimer = false, actionError = (result as? ApiResult.Failure)?.error) }
        }
    }

    fun stopTimer() {
        if (_state.value.isStoppingTimer || _state.value.runningTimer == null) {
            return
        }

        _state.update { it.copy(isStoppingTimer = true, actionError = null) }

        viewModelScope.launch {
            val result = timerTracker.stop()
            _state.update { it.copy(isStoppingTimer = false, actionError = (result as? ApiResult.Failure)?.error) }
        }
    }

    /** Uploads a photo, video or voice note; it goes on while the app is open, even off this screen. */
    fun attach(source: UploadSource, type: AttachmentType) {
        attachmentUploads.start(taskId, source, type)
    }

    fun retryUpload(id: Long) {
        attachmentUploads.retry(id)
    }

    fun dismissUpload(id: Long) {
        attachmentUploads.dismiss(id)
    }

    /** Deletes an attachment with its file; the Task reloads once the server confirms. */
    fun deleteAttachment(attachmentId: String) {
        if (_state.value.deletingAttachmentId != null) {
            return
        }

        _state.update { it.copy(deletingAttachmentId = attachmentId, actionError = null) }

        viewModelScope.launch {
            when (val result = attachmentsRepository.delete(taskId, attachmentId)) {
                is ApiResult.Success -> _state.update { state ->
                    state.copy(
                        deletingAttachmentId = null,
                        task = state.task?.let { task -> task.copy(attachments = task.attachments.filterNot { it.id == attachmentId }) },
                    )
                }
                is ApiResult.Failure -> _state.update { it.copy(deletingAttachmentId = null, actionError = result.error) }
            }
        }
    }

    fun dismissActionError() {
        _state.update { it.copy(actionError = null) }
    }

    private fun load(isRefresh: Boolean) {
        loadJob?.cancel()
        _state.update { it.copy(isLoading = !isRefresh && it.task == null, isRefreshing = isRefresh, error = null) }

        loadJob = viewModelScope.launch {
            when (val result = tasksRepository.task(taskId)) {
                is ApiResult.Success -> _state.update {
                    it.copy(task = result.value, isLoading = false, isRefreshing = false, error = null)
                }
                is ApiResult.Failure -> _state.update {
                    it.copy(isLoading = false, isRefreshing = false, error = result.error)
                }
            }
        }
    }
}
