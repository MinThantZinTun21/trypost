package com.minthantzintun.trypost.core.model

import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.ZoneId

/** A Task (ADR 0016): a to-do of the Owner's, kept on the server. */
data class Task(
    val id: String,
    val title: String,
    val description: String?,
    val status: TaskStatus,
    val priority: TaskPriority,
    val dueAt: Instant?,
    /** True when [dueAt] is the start of a day in the phone's timezone and the Task has no time. */
    val isDueAllDay: Boolean,
    val completedAt: Instant?,
    val createdAt: Instant?,
    val updatedAt: Instant?,
    /** Ordered by Tag group name, then Task tag name. */
    val taskTags: List<TaskTagRef>,
) {
    val isOpen: Boolean get() = status != TaskStatus.Done

    /** The due as the editor shows it, in [zone]. */
    fun due(zone: ZoneId): TaskDue? {
        val dueAt = dueAt ?: return null

        return if (isDueAllDay) TaskDue.AllDay(dueAt.atZone(zone).toLocalDate()) else TaskDue.Timed(dueAt.atZone(zone).toLocalDateTime())
    }

    /** The day the Task is due in [zone], null when it has no due. */
    fun dueDay(zone: ZoneId): LocalDate? = dueAt?.atZone(zone)?.toLocalDate()

    /** An open Task past its due: an all-day one once its day is over, a timed one once its time has passed. */
    fun isOverdue(now: Instant, zone: ZoneId): Boolean {
        val dueAt = dueAt ?: return false
        if (!isOpen) {
            return false
        }

        return if (isDueAllDay) dueAt.atZone(zone).toLocalDate().isBefore(now.atZone(zone).toLocalDate()) else dueAt.isBefore(now)
    }
}

/** When a Task is due: a whole day, or a day and a time, both in the phone's timezone. */
sealed interface TaskDue {
    val date: LocalDate

    data class AllDay(override val date: LocalDate) : TaskDue

    data class Timed(val at: LocalDateTime) : TaskDue {
        override val date: LocalDate get() = at.toLocalDate()
    }
}

enum class TaskStatus(val wire: String) {
    Todo("todo"),
    InProgress("in_progress"),
    Done("done"),
    ;

    companion object {
        fun fromWire(value: String): TaskStatus = entries.firstOrNull { it.wire == value } ?: Todo
    }
}

enum class TaskPriority(val wire: Int) {
    None(0),
    Low(1),
    Medium(2),
    High(3),
    ;

    companion object {
        fun fromWire(value: Int): TaskPriority = entries.firstOrNull { it.wire == value } ?: None
    }
}

/** The Tasks list tabs: Open is To do and In progress, Done is Done. */
enum class TaskTab(val wire: String) {
    Open("open"),
    Done("done"),
}

/** A Tag group's colour, one of the server's fixed palette. */
enum class TagColor(val wire: String) {
    Gray("gray"),
    Red("red"),
    Orange("orange"),
    Yellow("yellow"),
    Green("green"),
    Teal("teal"),
    Blue("blue"),
    Brown("brown"),
    ;

    companion object {
        fun fromWire(value: String?): TagColor = entries.firstOrNull { it.wire == value } ?: Gray
    }
}

/** A Task tag as a Task carries it, with its Tag group's name and colour. */
data class TaskTagRef(
    val id: String,
    val name: String,
    val tagGroupId: String,
    val tagGroupName: String,
    val color: TagColor,
)

/** A Tag group with its Task tags, ordered by name. */
data class TagGroup(
    val id: String,
    val name: String,
    val color: TagColor,
    val taskTags: List<TaskTag>,
)

data class TaskTag(
    val id: String,
    val name: String,
    val tagGroupId: String,
)

/** Every Task tag in [this] Tag groups, keyed by id, with its group's name and colour. */
fun List<TagGroup>.taskTagRefs(): Map<String, TaskTagRef> = flatMap { group ->
    group.taskTags.map { TaskTagRef(it.id, it.name, group.id, group.name, group.color) }
}.associateBy { it.id }

data class TaskPage(
    val tasks: List<Task>,
    val page: Int,
    val lastPage: Int,
)

/** What the editor saves: the whole Task, since a save replaces every field. */
data class TaskDraft(
    val title: String,
    val description: String?,
    val priority: TaskPriority,
    val due: TaskDue?,
    val taskTagIds: List<String>,
)
