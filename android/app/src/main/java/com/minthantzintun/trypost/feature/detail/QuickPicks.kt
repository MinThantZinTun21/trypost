package com.minthantzintun.trypost.feature.detail

import java.time.Duration
import java.time.Instant
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneId

enum class QuickPickKind { InOneHour, Tonight, TomorrowMorning, OneDayLater }

/** A one-tap Reschedule time. */
data class QuickPick(val kind: QuickPickKind, val at: Instant)

private val Tonight = LocalTime.of(20, 0)
private val TomorrowMorning = LocalTime.of(9, 0)
private const val ROUND_TO_SECONDS = 5 * 60L

/**
 * The Reschedule sheet's quick picks, in [zone]: in an hour (rounded up to
 * five minutes), tonight at 20:00, tomorrow at 09:00, and the Post's own time
 * a day later. Local times go through [java.time.ZonedDateTime], so a daylight
 * saving change keeps 09:00 at 09:00. A pick not in the future is dropped.
 */
fun quickReschedulePicks(now: Instant, current: Instant?, zone: ZoneId): List<QuickPick> {
    val today = LocalDate.ofInstant(now, zone)
    val inAnHour = now.plus(Duration.ofHours(1)).epochSecond.let { seconds ->
        Instant.ofEpochSecond((seconds + ROUND_TO_SECONDS - 1) / ROUND_TO_SECONDS * ROUND_TO_SECONDS)
    }

    return listOfNotNull(
        QuickPick(QuickPickKind.InOneHour, inAnHour),
        QuickPick(QuickPickKind.Tonight, today.atTime(Tonight).atZone(zone).toInstant()),
        QuickPick(QuickPickKind.TomorrowMorning, today.plusDays(1).atTime(TomorrowMorning).atZone(zone).toInstant()),
        current?.let { QuickPick(QuickPickKind.OneDayLater, it.atZone(zone).plusDays(1).toInstant()) },
    )
        .filter { it.at.isAfter(now) }
        .distinctBy { it.at }
}
