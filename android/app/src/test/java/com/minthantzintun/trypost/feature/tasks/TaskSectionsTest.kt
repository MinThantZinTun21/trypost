package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.ChecklistProgress
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant
import java.time.ZoneId

class TaskSectionsTest {
    private val yangon = ZoneId.of("Asia/Yangon")

    /** 10:00 on 2 Jan 2030 in Yangon. */
    private val now = Instant.parse("2030-01-02T03:30:00Z")

    private fun task(
        id: String,
        dueAt: String? = null,
        isAllDay: Boolean = false,
        status: TaskStatus = TaskStatus.Todo,
        isPinned: Boolean = false,
        title: String? = id,
        description: String? = null,
    ) = Task(
        id = id,
        title = title,
        description = description,
        status = status,
        priority = TaskPriority.None,
        dueAt = dueAt?.let(Instant::parse),
        isDueAllDay = isAllDay && dueAt != null,
        completedAt = null,
        color = null,
        isPinned = isPinned,
        createdAt = null,
        updatedAt = null,
        taskTags = emptyList(),
    )

    private fun List<TaskSection>.ids() = associate { section -> section.kind to section.tasks.map { it.id } }

    @Test
    fun `pinned Tasks come first, then overdue, today, upcoming and undated`() {
        val tasks = listOf(
            task("pinned-late", "2030-01-01T03:30:00Z", isPinned = true),
            task("late", "2030-01-02T03:00:00Z"),
            task("later-today", "2030-01-02T10:00:00Z"),
            task("tomorrow", "2030-01-03T03:30:00Z"),
            task("undated"),
            task("pinned-undated", isPinned = true),
        )

        val sections = groupTasksIntoSections(tasks, now, yangon)

        assertEquals(TaskSectionKind.entries, sections.map { it.kind })
        assertEquals(
            mapOf(
                TaskSectionKind.Pinned to listOf("pinned-late", "pinned-undated"),
                TaskSectionKind.Overdue to listOf("late"),
                TaskSectionKind.Today to listOf("later-today"),
                TaskSectionKind.Upcoming to listOf("tomorrow"),
                TaskSectionKind.NoDate to listOf("undated"),
            ),
            sections.ids(),
        )
    }

    @Test
    fun `days are read in the phone's timezone and empty sections are left out`() {
        val tasks = listOf(
            // 18:00 UTC on 2 Jan is 00:30 on 3 Jan in Yangon: tomorrow, not today.
            task("past-midnight", "2030-01-02T18:00:00Z"),
            // The start of 2 Jan in Yangon, all day: today, not overdue.
            task("all-day-today", "2030-01-01T17:30:00Z", isAllDay = true),
        )

        val sections = groupTasksIntoSections(tasks, now, yangon)

        assertEquals(
            mapOf(TaskSectionKind.Today to listOf("all-day-today"), TaskSectionKind.Upcoming to listOf("past-midnight")),
            sections.ids(),
        )
    }

    @Test
    fun `an all-day Task is overdue only once its day is over`() {
        val dueToday = task("today", "2030-01-01T17:30:00Z", isAllDay = true)
        val lateOnItsDay = Instant.parse("2030-01-02T16:00:00Z") // 22:30 on 2 Jan in Yangon
        val nextDay = Instant.parse("2030-01-02T18:00:00Z") // 00:30 on 3 Jan in Yangon

        assertFalse(dueToday.isOverdue(lateOnItsDay, yangon))
        assertTrue(dueToday.isOverdue(nextDay, yangon))
    }

    @Test
    fun `a timed Task is overdue once its time passes, unless it is Done`() {
        val timed = task("timed", "2030-01-02T10:00:00Z")
        val after = Instant.parse("2030-01-02T10:01:00Z")

        assertFalse(timed.isOverdue(Instant.parse("2030-01-02T09:59:00Z"), yangon))
        assertTrue(timed.isOverdue(after, yangon))
        assertFalse(timed.copy(status = TaskStatus.Done).isOverdue(after, yangon))
    }

    @Test
    fun `a card shows up to eight lines of text and counts the rest`() {
        val markdown = (1..11).joinToString("\n\n") { "Line $it" }

        val preview = taskCardPreview(markdown)!!

        assertEquals((1..8).map { "Line $it" }, preview.blocks.map { it.text.text })
        assertEquals(3, preview.moreCount)
        assertNull(preview.checklist)
    }

    @Test
    fun `a checklist card shows five rows, the rest as more, and the progress`() {
        val markdown = "Groceries\n" + (1..7).joinToString("\n") { if (it <= 2) "- [x] Item $it" else "- [ ] Item $it" }

        val preview = taskCardPreview(markdown)!!

        assertEquals(5, preview.blocks.size)
        assertEquals(BlockKind.Paragraph, preview.blocks.first().kind)
        assertEquals(3, preview.moreCount)
        assertEquals(ChecklistProgress(checked = 2, total = 7), preview.checklist)
    }

    @Test
    fun `a short body has nothing more and an empty one has no preview`() {
        assertEquals(0, taskCardPreview("- [ ]\n- [ ] Milk")!!.moreCount)
        assertNull(taskCardPreview(null))
        assertNull(taskCardPreview("  \n"))
    }

    @Test
    fun `a Task without a title is shown by its body's first line`() {
        assertEquals("Call mum", task("a", title = "Call mum", description = "Body").shownTitle())
        assertEquals("Buy milk", task("b", title = null, description = "\n- [ ] **Buy** milk\nEggs").shownTitle())
        assertNull(task("c", title = null, description = null).shownTitle())
    }
}
