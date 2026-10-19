package com.minthantzintun.trypost.core.model

import java.time.Duration
import java.time.Instant
import java.util.Locale

enum class TimerMode(val wire: String) {
    Pomodoro("pomodoro"),
    Free("free"),
    ;

    companion object {
        fun fromWire(value: String): TimerMode = entries.firstOrNull { it.wire == value } ?: Free
    }
}

/** How a Timer session ended: a Pomodoro that ran its length, or a free timer stopped, is Completed. */
enum class TimerOutcome(val wire: String) {
    Completed("completed"),
    Stopped("stopped"),
    ;

    companion object {
        fun fromWire(value: String?): TimerOutcome? = entries.firstOrNull { it.wire == value }
    }
}

/** One stretch of time spent on a Task. Its time is always worked out from [startedAt], never counted. */
data class TimerSession(
    val id: String,
    val taskId: String,
    val mode: TimerMode,
    val startedAt: Instant,
    val endedAt: Instant?,
    /** A Pomodoro's length; null for a free timer. */
    val plannedSeconds: Long?,
    val outcome: TimerOutcome?,
    /** The Task's title or body, when the server named it (the running timer). */
    val taskLabel: String? = null,
) {
    /** When a Pomodoro is up; null for a free timer. */
    val plannedEndAt: Instant? get() = plannedSeconds?.let { startedAt.plusSeconds(it) }

    /** Still counting at [now]: not stopped, and a Pomodoro not yet up. */
    fun isRunning(now: Instant): Boolean = endedAt == null && plannedEndAt?.let { now.isBefore(it) } != false

    fun elapsedSeconds(now: Instant): Long = TimerClock.elapsedSeconds(startedAt, endedAt ?: now, plannedSeconds)
}

/** The timer's arithmetic, kept apart so it is tested without a clock. */
object TimerClock {
    /** Whole seconds from [startedAt] to [until], never below 0, and never past a Pomodoro's [plannedSeconds]. */
    fun elapsedSeconds(startedAt: Instant, until: Instant, plannedSeconds: Long?): Long {
        val seconds = Duration.between(startedAt, until).seconds.coerceAtLeast(0)

        return plannedSeconds?.let { seconds.coerceAtMost(it) } ?: seconds
    }

    /** What is left of a Pomodoro at [now]; null for a free timer. */
    fun remainingSeconds(startedAt: Instant, now: Instant, plannedSeconds: Long?): Long? =
        plannedSeconds?.let { it - elapsedSeconds(startedAt, now, it) }

    /** `4:05`, or `1:02:03` from an hour on. */
    fun format(seconds: Long): String {
        val total = seconds.coerceAtLeast(0)
        val hours = total / 3600
        val minutes = total % 3600 / 60
        val rest = total % 60

        return if (hours > 0) "%d:%02d:%02d".format(Locale.ROOT, hours, minutes, rest) else "%d:%02d".format(Locale.ROOT, minutes, rest)
    }
}
