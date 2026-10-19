package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.ReminderTiming
import com.minthantzintun.trypost.core.model.TaskDue
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate
import java.time.LocalDateTime

class TaskRemindersTest {
    @Test
    fun `a timed due offers the due itself, ten minutes, an hour and a day before`() {
        val presets = reminderPresets(TaskDue.Timed(LocalDateTime.of(2030, 1, 2, 15, 0)))

        assertEquals(listOf(0, 10, 60, 1440).map { ReminderTiming.BeforeDue(it) }, presets)
    }

    @Test
    fun `a whole-day due offers nine in the morning of the day and of the day before`() {
        val presets = reminderPresets(TaskDue.AllDay(LocalDate.of(2030, 1, 2)))

        assertEquals(
            listOf(
                ReminderTiming.At(LocalDateTime.of(2030, 1, 2, 9, 0)),
                ReminderTiming.At(LocalDateTime.of(2030, 1, 1, 9, 0)),
            ),
            presets,
        )
    }

    @Test
    fun `no due offers only a picked day and time`() {
        assertTrue(reminderPresets(null).isEmpty())
    }
}
