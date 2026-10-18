package com.minthantzintun.trypost.feature.detail

import android.text.format.DateFormat
import androidx.annotation.DrawableRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.ListItem
import androidx.compose.material3.ListItemDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.ZoneId
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.time.temporal.ChronoUnit

private enum class ScheduleStep { None, Sheet, Date, Time, PostNow }

/**
 * The sticky bar under a Post that has not gone out: Reschedule (quick picks
 * first, then a date and a time in the phone's timezone) and, for a Scheduled
 * post, Post now after a confirm. A refused change shows above the buttons.
 */
@Composable
fun ScheduleBar(
    post: Post,
    isSaving: Boolean,
    actionError: ApiError?,
    onReschedule: (Instant) -> Unit,
    onPublishNow: () -> Unit,
) {
    if (!post.isEditable) {
        return
    }

    val zone = remember { ZoneId.systemDefault() }
    var step by rememberSaveable { mutableStateOf(ScheduleStep.None) }
    var pickedDate by rememberSaveable { mutableStateOf<LocalDate?>(null) }
    val initial = remember(post.scheduledAt) { initialSlot(post.scheduledAt, zone) }

    Surface(color = MaterialTheme.colorScheme.surfaceContainer, modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .navigationBarsPadding()
                .padding(horizontal = Spacing.gutter, vertical = Spacing.md),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            if (actionError != null) {
                ActionErrorMessages(post = post, error = actionError)
            }
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                Button(
                    onClick = { step = ScheduleStep.Sheet },
                    enabled = !isSaving,
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("reschedule"),
                ) {
                    if (isSaving) {
                        CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
                    } else {
                        Icon(painterResource(R.drawable.ic_reschedule), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    }
                    Text(stringResource(R.string.reschedule), modifier = Modifier.padding(start = Spacing.sm))
                }
                if (post.status == PostStatus.Scheduled) {
                    FilledTonalButton(
                        onClick = { step = ScheduleStep.PostNow },
                        enabled = !isSaving,
                        contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                        modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("post-now"),
                    ) {
                        Icon(painterResource(R.drawable.ic_send), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                        Text(stringResource(R.string.post_now), modifier = Modifier.padding(start = Spacing.sm))
                    }
                }
            }
        }
    }

    when (step) {
        ScheduleStep.None -> Unit
        ScheduleStep.Sheet -> RescheduleSheet(
            current = post.scheduledAt,
            zone = zone,
            onPick = {
                step = ScheduleStep.None
                onReschedule(it)
            },
            onCustom = { step = ScheduleStep.Date },
            onDismiss = { step = ScheduleStep.None },
        )
        ScheduleStep.PostNow -> ConfirmDialog(
            title = stringResource(R.string.post_now_confirm_title),
            body = stringResource(R.string.post_now_confirm_body),
            confirmLabel = stringResource(R.string.post_now),
            onConfirm = {
                step = ScheduleStep.None
                onPublishNow()
            },
            onDismiss = { step = ScheduleStep.None },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_send,
            confirmTestTag = "post-now-confirm",
        )
        ScheduleStep.Date -> DateStep(
            initial = pickedDate ?: initial.toLocalDate(),
            zone = zone,
            onPicked = {
                pickedDate = it
                step = ScheduleStep.Time
            },
            onDismiss = { step = ScheduleStep.None },
        )
        ScheduleStep.Time -> TimeStep(
            initial = initial.toLocalTime(),
            onPicked = { time ->
                val date = pickedDate ?: initial.toLocalDate()
                step = ScheduleStep.None
                pickedDate = null
                onReschedule(LocalDateTime.of(date, time).atZone(zone).toInstant())
            },
            onDismiss = { step = ScheduleStep.None },
        )
    }
}

/** The Post's own time when it has a future one, else the next full hour. */
private fun initialSlot(scheduledAt: Instant?, zone: ZoneId): LocalDateTime {
    val now = Instant.now()
    val start = scheduledAt?.takeIf { it.isAfter(now) } ?: now.plus(1, ChronoUnit.HOURS).truncatedTo(ChronoUnit.HOURS)

    return LocalDateTime.ofInstant(start, zone)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RescheduleSheet(
    current: Instant?,
    zone: ZoneId,
    onPick: (Instant) -> Unit,
    onCustom: () -> Unit,
    onDismiss: () -> Unit,
) {
    val picks = remember(current, zone) { quickReschedulePicks(Instant.now(), current, zone) }
    val formatter = remember { DateTimeFormatter.ofLocalizedDateTime(FormatStyle.MEDIUM, FormatStyle.SHORT) }
    val colors = ListItemDefaults.colors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = MaterialTheme.colorScheme.surfaceContainerLow,
    ) {
        Column(modifier = Modifier.padding(bottom = Spacing.lg).testTag("reschedule-sheet")) {
            Text(
                text = stringResource(R.string.reschedule_sheet_title),
                style = MaterialTheme.typography.titleLarge,
                modifier = Modifier
                    .padding(horizontal = Spacing.xl, vertical = Spacing.sm)
                    .semantics { heading() },
            )
            picks.forEach { pick ->
                ListItem(
                    headlineContent = { Text(stringResource(pick.kind.label())) },
                    supportingContent = { Text(pick.at.atZone(zone).format(formatter)) },
                    leadingContent = { Icon(painterResource(pick.kind.icon()), contentDescription = null) },
                    colors = colors,
                    modifier = Modifier
                        .clickableRow { onPick(pick.at) }
                        .testTag("reschedule-pick-${pick.kind.name}"),
                )
            }
            HorizontalDivider(modifier = Modifier.padding(vertical = Spacing.xs), color = MaterialTheme.colorScheme.outlineVariant)
            ListItem(
                headlineContent = { Text(stringResource(R.string.reschedule_custom)) },
                leadingContent = { Icon(painterResource(R.drawable.ic_calendar), contentDescription = null) },
                colors = colors,
                modifier = Modifier.clickableRow(onCustom).testTag("reschedule-custom"),
            )
        }
    }
}

private fun Modifier.clickableRow(onClick: () -> Unit): Modifier =
    this.then(Modifier.clickable(role = Role.Button, onClick = onClick))

@DrawableRes
private fun QuickPickKind.icon(): Int = when (this) {
    QuickPickKind.InOneHour -> R.drawable.ic_clock_plus
    QuickPickKind.Tonight -> R.drawable.ic_theme_dark
    QuickPickKind.TomorrowMorning -> R.drawable.ic_sunrise
    QuickPickKind.OneDayLater -> R.drawable.ic_repeat
}

private fun QuickPickKind.label(): Int = when (this) {
    QuickPickKind.InOneHour -> R.string.reschedule_in_one_hour
    QuickPickKind.Tonight -> R.string.reschedule_tonight
    QuickPickKind.TomorrowMorning -> R.string.reschedule_tomorrow_morning
    QuickPickKind.OneDayLater -> R.string.reschedule_one_day_later
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateStep(initial: LocalDate, zone: ZoneId, onPicked: (LocalDate) -> Unit, onDismiss: () -> Unit) {
    // DatePicker speaks in UTC midnights, so dates convert through UTC, not the phone's zone.
    val today = remember(zone) { LocalDate.now(zone) }
    val state = rememberDatePickerState(
        initialSelectedDateMillis = initial.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
        selectableDates = remember(today) {
            object : SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long): Boolean =
                    !Instant.ofEpochMilli(utcTimeMillis).atZone(ZoneOffset.UTC).toLocalDate().isBefore(today)

                override fun isSelectableYear(year: Int): Boolean = year >= today.year
            }
        },
    )

    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(
                onClick = {
                    state.selectedDateMillis?.let {
                        onPicked(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate())
                    }
                },
                enabled = state.selectedDateMillis != null,
                modifier = Modifier.testTag("schedule-date-next"),
            ) { Text(stringResource(R.string.next)) }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
        },
    ) {
        DatePicker(state = state)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TimeStep(initial: LocalTime, onPicked: (LocalTime) -> Unit, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val state = rememberTimePickerState(
        initialHour = initial.hour,
        initialMinute = initial.minute,
        is24Hour = DateFormat.is24HourFormat(context),
    )

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.schedule_pick_time)) },
        text = { TimePicker(state = state) },
        confirmButton = {
            Button(
                onClick = { onPicked(LocalTime.of(state.hour, state.minute)) },
                modifier = Modifier.testTag("schedule-time-save"),
            ) { Text(stringResource(R.string.schedule)) }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
        },
    )
}

@Composable
private fun ActionErrorMessages(post: Post, error: ApiError) {
    val lines = when (error) {
        is ApiError.Validation -> error.messagesFor(post)
            .map { line -> line.accountName?.let { "$it: ${line.message}" } ?: line.message }
            .ifEmpty { listOf(error.message()) }
        else -> listOf(error.message())
    }

    Row(
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
        modifier = Modifier.testTag("action-error"),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_status_failed),
            contentDescription = null,
            tint = MaterialTheme.colorScheme.error,
            modifier = Modifier.size(IconSize.sm),
        )
        Column {
            Text(
                text = stringResource(R.string.action_error_title),
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.error,
            )
            lines.forEach {
                Text(text = it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }
    }
}
