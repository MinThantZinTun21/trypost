package com.minthantzintun.trypost.core.reminders

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.Instant

class ReminderPlanTest {
    private val now = Instant.parse("2030-01-01T09:00:00Z")

    private fun reminder(id: String = "r1", at: String = "2030-01-01T10:00:00Z", repeats: Boolean = false) =
        UpcomingReminder(id = id, taskId = "t1", remindAtMillis = Instant.parse(at).toEpochMilli(), repeatsUntilDismissed = repeats, taskLabel = "Buy milk")

    private fun minutes(count: Long): Instant = now.plusSeconds(count * 60)

    @Test
    fun `a reminder still ahead rings at its time`() {
        assertEquals(Instant.parse("2030-01-01T10:00:00Z"), ReminderPlan.nextRing(reminder(), ring = null, now = now))
    }

    @Test
    fun `one missed while the phone was off rings at once`() {
        assertEquals(now, ReminderPlan.nextRing(reminder(at = "2030-01-01T08:00:00Z"), ring = null, now = now))
    }

    @Test
    fun `once rung it stays quiet, unless it repeats until dismissed`() {
        val past = reminder(at = "2030-01-01T08:00:00Z")
        val rang = ReminderPlan.rang(past, now)

        assertNull(ReminderPlan.nextRing(past, rang, now = minutes(1)))
        assertEquals(minutes(5), ReminderPlan.nextRing(past.copy(repeatsUntilDismissed = true), rang, now = minutes(1)))
    }

    @Test
    fun `a repeat that came due while the phone was off rings at once`() {
        val past = reminder(at = "2030-01-01T08:00:00Z", repeats = true)

        assertEquals(minutes(30), ReminderPlan.nextRing(past, ReminderPlan.rang(past, now), now = minutes(30)))
    }

    @Test
    fun `a snooze rings ten minutes later, and a repeat counts its five minutes from then`() {
        val past = reminder(at = "2030-01-01T08:00:00Z", repeats = true)
        val snoozed = ReminderPlan.snoozed(past, ReminderPlan.rang(past, now), now = minutes(2))

        assertEquals(minutes(12), ReminderPlan.nextRing(past, snoozed, now = minutes(3)))

        val rangAgain = ReminderPlan.rang(past, minutes(12))
        assertNull(rangAgain.snoozedUntilMillis)
        assertEquals(minutes(17), ReminderPlan.nextRing(past, rangAgain, now = minutes(12)))
    }

    @Test
    fun `a reminder moved to a new time forgets what it rang before`() {
        val past = reminder(at = "2030-01-01T08:00:00Z")
        val rang = ReminderPlan.rang(past, now)
        val moved = past.copy(remindAtMillis = Instant.parse("2030-01-01T11:00:00Z").toEpochMilli())

        assertEquals(Instant.parse("2030-01-01T11:00:00Z"), ReminderPlan.nextRing(moved, rang, now))
        assertEquals(emptyMap<String, ReminderRing>(), ReminderPlan.keptRings(listOf(moved), mapOf("r1" to rang)))
    }

    @Test
    fun `the re-arm list has one alarm per reminder that rings again`() {
        val ahead = reminder(id = "ahead")
        val rangOnce = reminder(id = "once", at = "2030-01-01T08:00:00Z")
        val repeating = reminder(id = "repeat", at = "2030-01-01T08:00:00Z", repeats = true)
        val missed = reminder(id = "missed", at = "2030-01-01T08:30:00Z")
        val rings = mapOf(
            "once" to ReminderPlan.rang(rangOnce, minutes(-30)),
            "repeat" to ReminderPlan.rang(repeating, minutes(-2)),
            "gone" to ReminderRing(remindAtMillis = 1),
        )

        val alarms = ReminderPlan.alarms(listOf(ahead, rangOnce, repeating, missed), ReminderPlan.keptRings(listOf(ahead, rangOnce, repeating, missed), rings), now)

        assertEquals(
            mapOf(
                "ahead" to Instant.parse("2030-01-01T10:00:00Z"),
                "repeat" to minutes(3),
                "missed" to now,
            ),
            alarms,
        )
    }

    @Test
    fun `a repeat that could not show stops, until a snooze brings it back`() {
        val repeating = reminder(at = "2030-01-01T08:00:00Z", repeats = true)
        val muted = ReminderPlan.rang(repeating, now, isShown = false)

        assertNull(ReminderPlan.nextRing(repeating, muted, minutes(5)))
        assertEquals(minutes(10), ReminderPlan.nextRing(repeating, ReminderPlan.snoozed(repeating, muted, now), now))
    }
}
