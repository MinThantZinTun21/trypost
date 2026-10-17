package com.minthantzintun.trypost.feature.detail

import android.text.format.DateFormat
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.rememberDatePickerState
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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.asLabel
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
import java.time.temporal.ChronoUnit

private enum class ScheduleStep { None, Date, Time, ConfirmUnschedule }

/**
 * Schedule or Reschedule (a date, then a time, in the phone's timezone) and
 * Unschedule after a confirmation. Only a Draft or Scheduled post shows them.
 */
@Composable
fun ScheduleActions(
    post: Post,
    isSaving: Boolean,
    actionError: ApiError?,
    onReschedule: (Instant) -> Unit,
    onUnschedule: () -> Unit,
    extraActions: @Composable () -> Unit = {},
) {
    if (!post.isEditable) {
        return
    }

    val zone = remember { ZoneId.systemDefault() }
    var step by rememberSaveable { mutableStateOf(ScheduleStep.None) }
    var pickedDate by rememberSaveable { mutableStateOf<LocalDate?>(null) }
    val initial = remember(post.scheduledAt) { initialSlot(post.scheduledAt, zone) }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(
                onClick = { step = ScheduleStep.Date },
                enabled = !isSaving,
                modifier = Modifier.testTag("reschedule"),
            ) {
                Text(stringResource(if (post.status == PostStatus.Scheduled) R.string.reschedule else R.string.schedule).asLabel())
            }
            if (post.status == PostStatus.Scheduled) {
                OutlinedButton(
                    onClick = { step = ScheduleStep.ConfirmUnschedule },
                    enabled = !isSaving,
                    modifier = Modifier.testTag("unschedule"),
                ) {
                    Text(stringResource(R.string.unschedule).asLabel())
                }
            }
            extraActions()
            if (isSaving) {
                CircularProgressIndicator(modifier = Modifier.size(24.dp), strokeWidth = 2.dp)
            }
        }

        if (actionError != null) {
            ActionErrorMessages(post = post, error = actionError)
        }
    }

    when (step) {
        ScheduleStep.None -> Unit
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
        ScheduleStep.ConfirmUnschedule -> AlertDialog(
            onDismissRequest = { step = ScheduleStep.None },
            title = { Text(stringResource(R.string.unschedule_confirm_title)) },
            text = { Text(stringResource(R.string.unschedule_confirm_body)) },
            confirmButton = {
                TextButton(
                    onClick = {
                        step = ScheduleStep.None
                        onUnschedule()
                    },
                    modifier = Modifier.testTag("unschedule-confirm"),
                ) { Text(stringResource(R.string.unschedule).asLabel()) }
            },
            dismissButton = {
                TextButton(onClick = { step = ScheduleStep.None }) { Text(stringResource(R.string.cancel).asLabel()) }
            },
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
            ) { Text(stringResource(R.string.next).asLabel()) }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel).asLabel()) }
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
            TextButton(
                onClick = { onPicked(LocalTime.of(state.hour, state.minute)) },
                modifier = Modifier.testTag("schedule-time-save"),
            ) { Text(stringResource(R.string.schedule).asLabel()) }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel).asLabel()) }
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

    Column(modifier = Modifier.testTag("action-error")) {
        Text(
            text = stringResource(R.string.action_error_title).asLabel(),
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.error,
        )
        lines.forEach {
            Text(text = it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
        }
    }
}
