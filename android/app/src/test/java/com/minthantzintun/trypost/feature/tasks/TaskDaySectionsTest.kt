package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

class TaskDaySectionsTest {
    private val yangon = ZoneId.of("Asia/Yangon")

    private fun task(id: String, dueAt: String?, isAllDay: Boolean = false, status: TaskStatus = TaskStatus.Todo) = Task(
        id = id,
        title = id,
        description = null,
        status = status,
        priority = TaskPriority.None,
        dueAt = dueAt?.let(Instant::parse),
        isDueAllDay = isAllDay && dueAt != null,
        completedAt = null,
        createdAt = null,
        updatedAt = null,
        taskTags = emptyList(),
    )

    @Test
    fun `Tasks group by their due day in the phone's timezone`() {
        val tasks = listOf(
            // 23:00 UTC on 1 Jan is 05:30 on 2 Jan in Yangon.
            task("late", "2030-01-01T23:00:00Z"),
            task("next", "2030-01-02T10:00:00Z"),
            task("third", "2030-01-03T01:00:00Z"),
        )

        val sections = groupTasksByDay(tasks, yangon)

        assertEquals(listOf(LocalDate.of(2030, 1, 2), LocalDate.of(2030, 1, 3)), sections.map { it.day })
        assertEquals(listOf("late", "next"), sections[0].items.map { it.id })
    }

    @Test
    fun `an all-day Task sits on its day with the timed ones`() {
        val tasks = listOf(
            // The start of 2 Jan in Yangon.
            task("all-day", "2030-01-01T17:30:00Z", isAllDay = true),
            task("timed", "2030-01-02T03:00:00Z"),
        )

        val sections = groupTasksByDay(tasks, yangon)

        assertEquals(1, sections.size)
        assertEquals(LocalDate.of(2030, 1, 2), sections.single().day)
        assertEquals(listOf("all-day", "timed"), sections.single().items.map { it.id })
    }

    @Test
    fun `undated Tasks come last in one No date section`() {
        val tasks = listOf(
            task("undated-1", null),
            task("dated", "2030-01-02T10:00:00Z"),
            task("undated-2", null),
        )

        val sections = groupTasksByDay(tasks, yangon)

        assertEquals(listOf(LocalDate.of(2030, 1, 2), null), sections.map { it.day })
        assertNull(sections.last().day)
        assertEquals(listOf("undated-1", "undated-2"), sections.last().items.map { it.id })
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
}
