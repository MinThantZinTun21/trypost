package com.minthantzintun.trypost.core.reminders

import kotlinx.serialization.Serializable
import java.time.Duration
import java.time.Instant

/** What this phone did with one reminder: when it last rang, and a Snooze. Only valid while the reminder keeps [remindAtMillis]. */
@Serializable
data class ReminderRing(
    val remindAtMillis: Long,
    val lastRangAtMillis: Long? = null,
    val snoozedUntilMillis: Long? = null,
    /** It rang where no notification could show, so it does not ring again every five minutes for nothing. */
    val isMuted: Boolean = false,
)

/**
 * When each reminder rings next. It is pure, so the time math and the
 * re-arm list are tested without a phone: a reminder rings at its time;
 * one missed while the phone was off rings at once; one that repeats until
 * dismissed rings again every five minutes; a Snooze moves the next ring.
 */
object ReminderPlan {
    val RepeatEvery: Duration = Duration.ofMinutes(5)
    val SnoozeFor: Duration = Duration.ofMinutes(10)

    /** The next ring of [reminder] at [now], or null when it has rung and does not repeat. */
    fun nextRing(reminder: UpcomingReminder, ring: ReminderRing?, now: Instant): Instant? {
        val remindAt = Instant.ofEpochMilli(reminder.remindAtMillis)
        val state = ring?.takeIf { it.remindAtMillis == reminder.remindAtMillis }

        state?.snoozedUntilMillis?.let { return Instant.ofEpochMilli(it).coerceAtLeast(now) }
        if (remindAt.isAfter(now)) {
            return remindAt
        }

        val lastRang = state?.lastRangAtMillis?.let(Instant::ofEpochMilli) ?: return now
        if (!reminder.repeatsUntilDismissed || state.isMuted) {
            return null
        }

        return lastRang.plus(RepeatEvery).coerceAtLeast(now)
    }

    /** The alarms to arm, keyed by reminder id: one per reminder that rings again. */
    fun alarms(upcoming: List<UpcomingReminder>, rings: Map<String, ReminderRing>, now: Instant): Map<String, Instant> =
        upcoming.mapNotNull { reminder -> nextRing(reminder, rings[reminder.id], now)?.let { reminder.id to it } }.toMap()

    /** The rings still worth keeping: of reminders still upcoming, at the same time. A moved reminder starts afresh. */
    fun keptRings(upcoming: List<UpcomingReminder>, rings: Map<String, ReminderRing>): Map<String, ReminderRing> {
        val times = upcoming.associate { it.id to it.remindAtMillis }

        return rings.filter { (id, ring) -> times[id] == ring.remindAtMillis }
    }

    /** The ring after [reminder] rang at [now]: any Snooze is used up, and one that was not [isShown] stops repeating. */
    fun rang(reminder: UpcomingReminder, now: Instant, isShown: Boolean = true): ReminderRing =
        ReminderRing(remindAtMillis = reminder.remindAtMillis, lastRangAtMillis = now.toEpochMilli(), isMuted = !isShown)

    /** [ring] after a Snooze at [now]; a repeating reminder counts its five minutes from the snoozed ring. */
    fun snoozed(reminder: UpcomingReminder, ring: ReminderRing?, now: Instant): ReminderRing =
        (ring?.takeIf { it.remindAtMillis == reminder.remindAtMillis } ?: ReminderRing(reminder.remindAtMillis))
            .copy(snoozedUntilMillis = now.plus(SnoozeFor).toEpochMilli(), lastRangAtMillis = now.toEpochMilli(), isMuted = false)

    private fun Instant.coerceAtLeast(other: Instant): Instant = if (isBefore(other)) other else this
}
