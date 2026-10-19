package com.minthantzintun.trypost.core.model

import java.time.Instant
import java.time.LocalDateTime
import java.time.ZoneId

/** A Reminder on a Task (spec #130): the phone rings at [remindAt] until it is dismissed or the Task is done. */
data class Reminder(
    val id: String,
    val taskId: String,
    val remindAt: Instant,
    /** Set when the reminder follows the Task's due; null for one at a set time. */
    val minutesBefore: Int?,
    /** Rings again every few minutes until the Owner dismisses it. */
    val repeatsUntilDismissed: Boolean,
    val dismissedAt: Instant?,
) {
    /** The reminder as the editor holds it, in [zone]. */
    fun toDraft(zone: ZoneId): ReminderDraft = ReminderDraft(
        timing = minutesBefore?.let { ReminderTiming.BeforeDue(it) } ?: ReminderTiming.At(remindAt.atZone(zone).toLocalDateTime()),
        repeatsUntilDismissed = repeatsUntilDismissed,
    )
}

/** When a reminder rings: at a day and time in the phone's timezone, or some minutes before the due. */
sealed interface ReminderTiming {
    data class At(val at: LocalDateTime) : ReminderTiming

    data class BeforeDue(val minutes: Int) : ReminderTiming
}

/** A reminder in a save; a save sends the Task's whole set. */
data class ReminderDraft(
    val timing: ReminderTiming,
    val repeatsUntilDismissed: Boolean = false,
)
