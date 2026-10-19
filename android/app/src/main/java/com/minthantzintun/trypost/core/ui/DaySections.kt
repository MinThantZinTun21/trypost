package com.minthantzintun.trypost.core.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.Spacing
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

/** Items that fall on one day in the phone's timezone; `day` is null for undated items. */
data class DaySection<T>(
    val day: LocalDate?,
    val items: List<T>,
)

/**
 * Groups items by [dayOf], keeping the server's order. A day that comes back
 * later (an item out of order, or one that moved between pages) joins its
 * first section, so each day is listed once and keys its header uniquely.
 */
fun <T> groupByDay(items: List<T>, dayOf: (T) -> LocalDate?): List<DaySection<T>> =
    items
        .groupBy(dayOf)
        .map { (day, itemsOnDay) -> DaySection(day = day, items = itemsOnDay) }

/** A sticky day header: Today, Tomorrow, Yesterday, else the date; "No date" for undated items. */
@Composable
fun DayHeader(day: LocalDate?, modifier: Modifier = Modifier) {
    val today = LocalDate.now()
    val label = when (day) {
        null -> stringResource(R.string.posts_no_date)
        today -> stringResource(R.string.posts_today)
        today.plusDays(1) -> stringResource(R.string.posts_tomorrow)
        today.minusDays(1) -> stringResource(R.string.posts_yesterday)
        else -> day.format(
            if (day.year == today.year) DateTimeFormatter.ofPattern("EEEE, d MMM") else DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM),
        )
    }

    Text(
        text = label,
        style = MaterialTheme.typography.titleSmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surface)
            .padding(top = Spacing.md, bottom = Spacing.xs, start = Spacing.xs)
            .semantics { heading() },
    )
}
