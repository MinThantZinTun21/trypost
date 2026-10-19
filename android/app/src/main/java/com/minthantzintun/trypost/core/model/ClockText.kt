package com.minthantzintun.trypost.core.model

import java.util.Locale

/** A length of time as a clock shows it, for a voice note or a video. */
object ClockText {
    /** `4:05`, or `1:02:03` from an hour on. */
    fun format(seconds: Long): String {
        val total = seconds.coerceAtLeast(0)
        val hours = total / 3600
        val minutes = total % 3600 / 60
        val rest = total % 60

        return if (hours > 0) "%d:%02d:%02d".format(Locale.ROOT, hours, minutes, rest) else "%d:%02d".format(Locale.ROOT, minutes, rest)
    }
}
