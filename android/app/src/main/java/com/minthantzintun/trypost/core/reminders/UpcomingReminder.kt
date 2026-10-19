package com.minthantzintun.trypost.core.reminders

import kotlinx.serialization.Serializable

/**
 * A reminder the phone arms, as `reminders/upcoming` lists it, with what its
 * notification says about the Task, so it rings with no connection.
 */
@Serializable
data class UpcomingReminder(
    val id: String,
    val taskId: String,
    val remindAtMillis: Long,
    val repeatsUntilDismissed: Boolean,
    /** The Task's title, else its body's first line. */
    val taskLabel: String?,
)
