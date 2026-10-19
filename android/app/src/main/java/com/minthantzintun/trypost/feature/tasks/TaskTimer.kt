package com.minthantzintun.trypost.feature.tasks

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TimerClock
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.model.TimerSession
import kotlinx.coroutines.delay
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

/** How many past sessions the detail screen lists. */
private const val ShownSessions = 5

/**
 * The Task's timer: Pomodoro (the length in Settings) or a free timer, the
 * running one with its time and Stop, then the time tracked and the latest
 * sessions. Every time shown is worked out from a session's start.
 */
@Composable
fun TimerSection(
    task: Task,
    state: TaskDetailUiState,
    onStart: (TimerMode) -> Unit,
    onStop: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var now by remember { mutableStateOf(Instant.now()) }
    val running = state.runningTimer
    val isTicking = running != null || task.timerSessions.any { it.isRunning(now) }

    LaunchedEffect(isTicking) {
        while (isTicking) {
            now = Instant.now()
            delay(1_000)
        }
    }

    Column(modifier = modifier.testTag("task-timer"), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        if (running != null) {
            RunningTimer(running, now = now, isStopping = state.isStoppingTimer, onStop = onStop)
        } else {
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), modifier = Modifier.fillMaxWidth()) {
                FilledTonalButton(
                    onClick = { onStart(TimerMode.Pomodoro) },
                    enabled = !state.isStartingTimer,
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("task-timer-pomodoro"),
                ) {
                    ButtonLabel(
                        icon = R.drawable.ic_clock,
                        label = stringResource(R.string.timer_start_pomodoro, state.pomodoroFocusMinutes),
                        isBusy = state.isStartingTimer,
                    )
                }
                OutlinedButton(
                    onClick = { onStart(TimerMode.Free) },
                    enabled = !state.isStartingTimer,
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    modifier = Modifier.weight(1f).height(IconSize.touchTarget).testTag("task-timer-free"),
                ) {
                    ButtonLabel(icon = R.drawable.ic_play, label = stringResource(R.string.timer_start_free))
                }
            }
            if (state.isTimerElsewhere) {
                Text(
                    text = stringResource(R.string.timer_running_elsewhere),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        if (task.timerSessions.isNotEmpty()) {
            val trackedSeconds = task.timerSessions.sumOf { it.elapsedSeconds(now) }
            Text(
                text = stringResource(
                    R.string.timer_tracked,
                    TimerClock.format(trackedSeconds),
                    pluralStringResource(R.plurals.timer_sessions, task.timerSessions.size, task.timerSessions.size),
                ),
                style = MaterialTheme.typography.bodyMedium,
                modifier = Modifier.testTag("task-timer-tracked"),
            )
            SessionList(task.timerSessions.take(ShownSessions), now = now)
        }
    }
}

@Composable
private fun RunningTimer(session: TimerSession, now: Instant, isStopping: Boolean, onStop: () -> Unit) {
    val remaining = TimerClock.remainingSeconds(session.startedAt, now, session.plannedSeconds)

    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.md), modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = TimerClock.format(remaining ?: session.elapsedSeconds(now)),
                style = MaterialTheme.typography.headlineMedium,
                modifier = Modifier
                    .semantics { liveRegion = LiveRegionMode.Polite }
                    .testTag("task-timer-time"),
            )
            Text(
                text = stringResource(if (remaining != null) R.string.timer_pomodoro_left else R.string.timer_free_running),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        FilledTonalButton(
            onClick = onStop,
            enabled = !isStopping,
            contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
            modifier = Modifier.height(IconSize.touchTarget).testTag("task-timer-stop"),
        ) {
            ButtonLabel(icon = R.drawable.ic_player_stop, label = stringResource(R.string.timer_stop), isBusy = isStopping)
        }
    }
}

@Composable
private fun SessionList(sessions: List<TimerSession>, now: Instant) {
    val zone = remember { ZoneId.systemDefault() }
    val today = remember { LocalDate.now(zone) }

    Column(verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
        sessions.forEach { session ->
            val started = dueLabel(TaskDue.Timed(session.startedAt.atZone(zone).toLocalDateTime()), today)
            Text(
                text = stringResource(
                    R.string.timer_session_line,
                    started,
                    stringResource(if (session.mode == TimerMode.Pomodoro) R.string.timer_mode_pomodoro else R.string.timer_mode_free),
                    TimerClock.format(session.elapsedSeconds(now)),
                ),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}
