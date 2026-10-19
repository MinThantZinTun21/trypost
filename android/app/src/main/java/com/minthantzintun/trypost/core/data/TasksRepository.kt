package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.AttachmentType
import com.minthantzintun.trypost.core.model.Reminder
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.ReminderTiming
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TagGroup
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskAttachment
import com.minthantzintun.trypost.core.model.TaskBody
import com.minthantzintun.trypost.core.model.TaskDraft
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPage
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.model.TaskTag
import com.minthantzintun.trypost.core.model.TaskTagRef
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.ReminderBody
import com.minthantzintun.trypost.core.network.ReminderDto
import com.minthantzintun.trypost.core.network.SaveTaskBody
import com.minthantzintun.trypost.core.network.TagGroupBody
import com.minthantzintun.trypost.core.network.TagGroupDto
import com.minthantzintun.trypost.core.network.TaskAttachmentDto
import com.minthantzintun.trypost.core.network.TaskDto
import com.minthantzintun.trypost.core.network.TaskStatusBody
import com.minthantzintun.trypost.core.network.TaskTagBody
import com.minthantzintun.trypost.core.network.TaskTagDto
import com.minthantzintun.trypost.core.network.map
import com.minthantzintun.trypost.core.reminders.UpcomingReminder
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import javax.inject.Inject
import javax.inject.Singleton

/** A change this app just made, so a screen still on the back stack can reload. */
sealed interface TaskChange {
    data class Saved(val task: Task) : TaskChange

    data class Deleted(val id: String) : TaskChange

    /** A Tag group or Task tag changed, which can change how every Task's Task tags read. */
    data object TaskTagsChanged : TaskChange

}

/** The Owner's Tasks, Tag groups and Task tags (ADR 0016), online only like Posts and Videos. */
@Singleton
class TasksRepository @Inject constructor(
    private val client: MobileClient,
) {
    private val _changes = MutableSharedFlow<TaskChange>(extraBufferCapacity = 8)

    val changes: SharedFlow<TaskChange> = _changes.asSharedFlow()

    /** One page of a tab, only Tasks with [taskTagIds] (the server reads them per Tag group) and matching [query]. */
    suspend fun page(tab: TaskTab, page: Int, taskTagIds: List<String> = emptyList(), query: String? = null): ApiResult<TaskPage> =
        client.call {
            tasks(tab = tab.wire, page = page, taskTags = taskTagIds, query = query?.trim()?.takeIf { it.isNotEmpty() })
        }.map { dto ->
            TaskPage(tasks = dto.data.map { it.toTask() }, page = dto.meta.currentPage, lastPage = dto.meta.lastPage)
        }

    suspend fun task(id: String): ApiResult<Task> = client.call { task(id) }.map { it.toTask() }

    /** Creates a Task; its due is sent with [zone]'s UTC offset. */
    suspend fun create(draft: TaskDraft, zone: ZoneId): ApiResult<Task> =
        client.call { createTask(draft.toBody(zone)) }.map { it.toTask() }.announced()

    /** Replaces every field of a Task; its due is sent with [zone]'s UTC offset. */
    suspend fun update(id: String, draft: TaskDraft, zone: ZoneId): ApiResult<Task> =
        client.call { updateTask(id, draft.toBody(zone)) }.map { it.toTask() }.announced()

    /** Done sets the completed time on the server; any other status clears it. */
    suspend fun setStatus(id: String, status: TaskStatus): ApiResult<Task> =
        client.call { updateTaskStatus(id, TaskStatusBody(status.wire)) }.map { it.toTask() }.announced()

    suspend fun delete(id: String): ApiResult<Unit> = client.call { deleteTask(id) }.also {
        if (it is ApiResult.Success) {
            _changes.tryEmit(TaskChange.Deleted(id))
        }
    }

    /** Every Tag group with its Task tags, both ordered by name. */
    suspend fun tagGroups(): ApiResult<List<TagGroup>> = client.call { tagGroups() }.map { dto -> dto.data.map { it.toTagGroup() } }

    suspend fun createTagGroup(name: String, color: TagColor): ApiResult<TagGroup> =
        client.call { createTagGroup(TagGroupBody(name.trim(), color.wire)) }.map { it.toTagGroup() }.taggedChange()

    suspend fun updateTagGroup(id: String, name: String, color: TagColor): ApiResult<TagGroup> =
        client.call { updateTagGroup(id, TagGroupBody(name.trim(), color.wire)) }.map { it.toTagGroup() }.taggedChange()

    /** Deletes the Tag group and its Task tags, which leave every Task. */
    suspend fun deleteTagGroup(id: String): ApiResult<Unit> = client.call { deleteTagGroup(id) }.taggedChange()

    suspend fun createTaskTag(tagGroupId: String, name: String): ApiResult<TaskTag> =
        client.call { createTaskTag(tagGroupId, TaskTagBody(name.trim())) }.map { it.toTaskTag() }.taggedChange()

    suspend fun renameTaskTag(id: String, name: String): ApiResult<TaskTag> =
        client.call { updateTaskTag(id, TaskTagBody(name.trim())) }.map { it.toTaskTag() }.taggedChange()

    /** Deletes the Task tag, which leaves every Task. */
    suspend fun deleteTaskTag(id: String): ApiResult<Unit> = client.call { deleteTaskTag(id) }.taggedChange()

    /** Every undismissed reminder of an open Task, past ones included, with the Task's title and body. */
    suspend fun upcomingReminders(): ApiResult<List<UpcomingReminder>> = client.call { upcomingReminders() }.map { dto ->
        dto.data.mapNotNull { reminder ->
            UpcomingReminder(
                id = reminder.id,
                taskId = reminder.taskId,
                remindAtMillis = reminder.remindAt.toInstant()?.toEpochMilli() ?: return@mapNotNull null,
                repeatsUntilDismissed = reminder.repeatUntilDismissed,
                taskLabel = reminder.task?.let { taskLabel(it.title, it.description) },
            )
        }
    }

    /** Stops a reminder for good; Snooze never reaches the server. */
    suspend fun dismissReminder(id: String): ApiResult<Unit> = client.call { dismissReminder(id) }.map { }

    /** Tells every screen a Task changed in a way only the server knows, such as a new attachment. */
    internal fun announce(task: Task) {
        _changes.tryEmit(TaskChange.Saved(task))
    }

    private fun ApiResult<Task>.announced(): ApiResult<Task> = also {
        if (it is ApiResult.Success) {
            _changes.tryEmit(TaskChange.Saved(it.value))
        }
    }

    private fun <T> ApiResult<T>.taggedChange(): ApiResult<T> = also {
        if (it is ApiResult.Success) {
            _changes.tryEmit(TaskChange.TaskTagsChanged)
        }
    }
}

/** `2030-01-01T15:00:00+06:30`: the server needs the offset, and `+00:00` rather than `Z`. */
private val WireDateTime: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ssxxx")

/** The wire `due_at` for [due] in [zone]: an all-day due is the start of its day there. */
internal fun dueAtWire(due: TaskDue, zone: ZoneId): String {
    val at: ZonedDateTime = when (due) {
        is TaskDue.AllDay -> due.date.atStartOfDay(zone)
        is TaskDue.Timed -> due.at.atZone(zone)
    }

    return at.format(WireDateTime)
}

private fun TaskDraft.toBody(zone: ZoneId): SaveTaskBody = SaveTaskBody(
    title = title.trim().takeIf { it.isNotEmpty() },
    description = description?.takeIf { it.isNotBlank() },
    priority = priority.wire,
    color = color?.wire,
    pinned = isPinned,
    dueAt = due?.let { dueAtWire(it, zone) },
    dueAllDay = due is TaskDue.AllDay,
    taskTags = taskTagIds,
    reminders = reminders?.map { it.toBody(zone) },
)

private fun ReminderDraft.toBody(zone: ZoneId): ReminderBody = when (val timing = timing) {
    is ReminderTiming.At -> ReminderBody(type = "at", remindAt = timing.at.atZone(zone).format(WireDateTime), repeatUntilDismissed = repeatsUntilDismissed)
    is ReminderTiming.BeforeDue -> ReminderBody(type = "before_due", minutesBefore = timing.minutes, repeatUntilDismissed = repeatsUntilDismissed)
}

internal fun TaskDto.toTask(): Task = Task(
    id = id,
    title = title?.takeIf { it.isNotBlank() },
    description = description?.takeIf { it.isNotEmpty() },
    status = TaskStatus.fromWire(status),
    priority = TaskPriority.fromWire(priority),
    dueAt = dueAt.toInstant(),
    isDueAllDay = dueAllDay && dueAt != null,
    completedAt = completedAt.toInstant(),
    color = TagColor.fromWireOrNull(color),
    isPinned = pinned,
    createdAt = createdAt.toInstant(),
    updatedAt = updatedAt.toInstant(),
    taskTags = taskTags.map {
        TaskTagRef(
            id = it.id,
            name = it.name,
            tagGroupId = it.tagGroupId,
            tagGroupName = it.tagGroupName,
            color = TagColor.fromWire(it.color),
        )
    },
    reminders = reminders.mapNotNull { it.toReminder() },
    attachments = attachments.map { it.toTaskAttachment() },
)

internal fun ReminderDto.toReminder(): Reminder? = Reminder(
    id = id,
    taskId = taskId,
    remindAt = remindAt.toInstant() ?: return null,
    minutesBefore = minutesBefore.takeIf { type == "before_due" },
    repeatsUntilDismissed = repeatUntilDismissed,
    dismissedAt = dismissedAt.toInstant(),
)

internal fun TaskAttachmentDto.toTaskAttachment(): TaskAttachment = TaskAttachment(
    id = id,
    type = AttachmentType.fromWire(type),
    url = url,
    mimeType = mimeType,
    fileName = originalFilename ?: fileName,
    size = size,
    durationSeconds = duration,
    createdAt = createdAt.toInstant(),
    download = downloadLink(downloadUrl, fileName, mimeType),
)

/** How a notification names a Task: its title, else its body's first line as plain text. */
internal fun taskLabel(title: String?, description: String?): String? =
    title?.takeIf { it.isNotBlank() } ?: description?.let { TaskBody.parse(it).blocks.firstOrNull { block -> block.text.text.isNotBlank() }?.text?.text }

private fun TagGroupDto.toTagGroup(): TagGroup = TagGroup(
    id = id,
    name = name,
    color = TagColor.fromWire(color),
    taskTags = taskTags.map { it.toTaskTag() },
)

private fun TaskTagDto.toTaskTag(): TaskTag = TaskTag(id = id, name = name, tagGroupId = tagGroupId)
