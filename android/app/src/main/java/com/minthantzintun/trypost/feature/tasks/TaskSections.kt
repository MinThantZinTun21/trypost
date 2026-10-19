package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.BodyBlock
import com.minthantzintun.trypost.core.model.ChecklistProgress
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskBody
import java.time.Instant
import java.time.ZoneId

/** The Open tab's sections, in the order they show. */
enum class TaskSectionKind { Pinned, Overdue, Today, Upcoming, NoDate }

data class TaskSection(val kind: TaskSectionKind, val tasks: List<Task>)

/**
 * Splits open Tasks into Pinned, Overdue, Today, Upcoming and No date by the
 * phone's date in [zone], keeping the server's order inside each one. A
 * pinned Task shows only under Pinned. Empty sections are left out.
 */
fun groupTasksIntoSections(tasks: List<Task>, now: Instant, zone: ZoneId): List<TaskSection> {
    val today = now.atZone(zone).toLocalDate()
    val byKind = tasks.groupBy { task ->
        val day = task.dueDay(zone)
        when {
            task.isPinned -> TaskSectionKind.Pinned
            day == null -> TaskSectionKind.NoDate
            task.isOverdue(now, zone) -> TaskSectionKind.Overdue
            day == today -> TaskSectionKind.Today
            day.isAfter(today) -> TaskSectionKind.Upcoming
            // A past day that is not overdue cannot happen for an open Task; keep it in view.
            else -> TaskSectionKind.Overdue
        }
    }

    return TaskSectionKind.entries.mapNotNull { kind -> byKind[kind]?.let { TaskSection(kind, it) } }
}

/** What a card shows of a Task's body: a few lines, how many more there are, and the checklist's progress. */
data class TaskCardPreview(
    val blocks: List<BodyBlock>,
    /** The lines the card leaves out. */
    val moreCount: Int,
    val checklist: ChecklistProgress?,
)

/** A card shows this many lines of a body, or this many rows of a checklist. */
const val CARD_MAX_LINES = 8
const val CARD_MAX_CHECKLIST_ROWS = 5

/**
 * The card's view of [description]: blank lines dropped, then up to
 * [CARD_MAX_CHECKLIST_ROWS] rows when the body has a checklist (Keep's
 * checklist cards), or [CARD_MAX_LINES] lines otherwise.
 */
fun taskCardPreview(description: String?): TaskCardPreview? {
    if (description.isNullOrBlank()) {
        return null
    }

    val body = TaskBody.parse(description)
    val lines = body.blocks.filter { it.text.text.isNotBlank() || it.kind == BlockKind.Checkbox }
    val checklist = body.checklist()
    val max = if (checklist != null) CARD_MAX_CHECKLIST_ROWS else CARD_MAX_LINES

    return TaskCardPreview(blocks = lines.take(max), moreCount = (lines.size - max).coerceAtLeast(0), checklist = checklist)
}

/** The Task's title, else its body's first line as plain text, else null. */
fun Task.shownTitle(): String? = title ?: description?.let { markdown ->
    TaskBody.parse(markdown).blocks.firstOrNull { it.text.text.isNotBlank() }?.text?.text?.trim()
}
