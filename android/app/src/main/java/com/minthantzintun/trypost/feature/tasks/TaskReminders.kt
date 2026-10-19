package com.minthantzintun.trypost.feature.tasks

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.ReminderTiming
import com.minthantzintun.trypost.core.model.TaskDue
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime

/** A reminder's quick choices for [due]: before a timed due, or the morning of (and before) a whole day. */
fun reminderPresets(due: TaskDue?): List<ReminderTiming> = when (due) {
    null -> emptyList()
    is TaskDue.Timed -> listOf(0, 10, 60, 24 * 60).map { ReminderTiming.BeforeDue(it) }
    is TaskDue.AllDay -> listOf(
        ReminderTiming.At(LocalDateTime.of(due.date, MorningReminder)),
        ReminderTiming.At(LocalDateTime.of(due.date.minusDays(1), MorningReminder)),
    )
}

private val MorningReminder: LocalTime = LocalTime.of(9, 0)

private enum class ReminderPickStep { None, Date, Time }

/**
 * A Task's reminders: each with Remove, an Add menu of [reminderPresets]
 * or a picked day and time, and "Keep ringing until dismissed" for them
 * all. The first one added asks for the notification permission.
 * [dismissed] marks the reminders already dismissed, by index.
 */
@Composable
fun ReminderEditor(
    reminders: List<ReminderDraft>,
    due: TaskDue?,
    onChange: (List<ReminderDraft>) -> Unit,
    enabled: Boolean,
    modifier: Modifier = Modifier,
    dismissed: Set<Int> = emptySet(),
) {
    val context = LocalContext.current
    val today = remember { LocalDate.now() }
    var isMenuOpen by remember { mutableStateOf(false) }
    var pickStep by rememberSaveable { mutableStateOf(ReminderPickStep.None) }
    var pickedDate by rememberSaveable { mutableStateOf<LocalDate?>(null) }
    val repeats = reminders.any { it.repeatsUntilDismissed }
    val notificationPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}

    val add = { timing: ReminderTiming ->
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
        onChange(reminders + ReminderDraft(timing, repeatsUntilDismissed = repeats))
    }

    Column(modifier = modifier.testTag("task-reminders"), verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
        reminders.forEachIndexed { index, reminder ->
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().testTag("task-reminder-$index")) {
                Icon(
                    painter = painterResource(R.drawable.ic_bell),
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(IconSize.sm),
                )
                Column(modifier = Modifier.weight(1f).padding(horizontal = Spacing.sm)) {
                    Text(reminderLabel(reminder.timing, today), style = MaterialTheme.typography.bodyMedium)
                    if (index in dismissed) {
                        Text(
                            text = stringResource(R.string.reminder_dismissed),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                ActionIconButton(
                    icon = R.drawable.ic_close,
                    label = stringResource(R.string.reminder_remove),
                    onClick = { onChange(reminders.filterIndexed { other, _ -> other != index }) },
                    enabled = enabled,
                    modifier = Modifier.testTag("task-reminder-remove-$index"),
                )
            }
        }

        if (reminders.size < MaxReminders) {
            Box {
                OutlinedButton(
                    onClick = { isMenuOpen = true },
                    enabled = enabled,
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    modifier = Modifier.testTag("task-reminder-add"),
                ) {
                    ButtonLabel(icon = R.drawable.ic_bell, label = stringResource(R.string.reminder_add))
                }
                DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
                    reminderPresets(due).filterNot { preset -> reminders.any { it.timing == preset } }.forEach { preset ->
                        DropdownMenuItem(
                            text = { Text(reminderLabel(preset, today)) },
                            onClick = {
                                isMenuOpen = false
                                add(preset)
                            },
                        )
                    }
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.reminder_pick)) },
                        leadingIcon = { Icon(painterResource(R.drawable.ic_calendar), contentDescription = null) },
                        onClick = {
                            isMenuOpen = false
                            pickStep = ReminderPickStep.Date
                        },
                        modifier = Modifier.testTag("task-reminder-pick"),
                    )
                }
            }
        }

        if (reminders.isNotEmpty()) {
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(stringResource(R.string.reminder_repeat), style = MaterialTheme.typography.bodyMedium)
                    Text(
                        text = stringResource(R.string.reminder_repeat_hint),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Switch(
                    checked = repeats,
                    onCheckedChange = { isOn -> onChange(reminders.map { it.copy(repeatsUntilDismissed = isOn) }) },
                    enabled = enabled,
                    modifier = Modifier.testTag("task-reminder-repeat"),
                )
            }
        }
    }

    when (pickStep) {
        ReminderPickStep.None -> Unit
        ReminderPickStep.Date -> DueDateDialog(
            initial = pickedDate ?: due?.date ?: today,
            onPicked = {
                pickedDate = it
                pickStep = ReminderPickStep.Time
            },
            onDismiss = { pickStep = ReminderPickStep.None },
        )
        ReminderPickStep.Time -> DueTimeDialog(
            initial = (due as? TaskDue.Timed)?.at?.toLocalTime() ?: MorningReminder,
            onPicked = { time ->
                pickStep = ReminderPickStep.None
                pickedDate?.let { add(ReminderTiming.At(LocalDateTime.of(it, time))) }
            },
            onDismiss = { pickStep = ReminderPickStep.None },
        )
    }
}

/** "At the due", "10 minutes before", "2 hours before", "1 day before", or the day and time. */
@Composable
fun reminderLabel(timing: ReminderTiming, today: LocalDate): String = when (timing) {
    is ReminderTiming.At -> dueLabel(TaskDue.Timed(timing.at), today)
    is ReminderTiming.BeforeDue -> when {
        timing.minutes == 0 -> stringResource(R.string.reminder_at_due)
        timing.minutes % (24 * 60) == 0 -> pluralStringResource(R.plurals.reminder_days_before, timing.minutes / (24 * 60), timing.minutes / (24 * 60))
        timing.minutes % 60 == 0 -> pluralStringResource(R.plurals.reminder_hours_before, timing.minutes / 60, timing.minutes / 60)
        else -> pluralStringResource(R.plurals.reminder_minutes_before, timing.minutes, timing.minutes)
    }
}

/** The server keeps up to 10 reminders on a Task. */
private const val MaxReminders = 10
