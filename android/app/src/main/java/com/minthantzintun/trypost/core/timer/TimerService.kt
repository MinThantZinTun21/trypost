package com.minthantzintun.trypost.core.timer

import android.Manifest
import android.app.AlarmManager
import android.app.Notification
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.model.TimerSession
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.notifications.AppNotifications
import com.minthantzintun.trypost.core.settings.SettingsStore
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import java.time.Clock
import java.time.Duration
import java.time.Instant
import javax.inject.Inject

/**
 * Keeps the running timer's notification up (a `specialUse` foreground
 * service, only while a session runs): the Task, the time counting from its
 * start (or down to a Pomodoro's end) and Stop. The system clock in the
 * notification does the counting, so nothing ticks here.
 */
@AndroidEntryPoint
class TimerService : Service() {
    @Inject
    lateinit var tracker: TimerTracker

    @Inject
    lateinit var settingsStore: SettingsStore

    @Inject
    lateinit var clock: Clock

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private var watchJob: Job? = null
    private var pomodoroJob: Job? = null
    private var shownSessionId: String? = null
    private var endedSessionId: String? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        // A service started for the foreground must show its notification first, even to stop at once.
        startInForeground(tracker.running.value?.let(::notification) ?: placeholder())

        when (intent?.action) {
            ACTION_STOP -> stopTimer()
            ACTION_POMODORO_END -> tracker.running.value?.let(::endPomodoroIfUp)
        }
        if (watchJob == null) {
            watchJob = scope.launch { tracker.running.collect(::follow) }
        }

        return START_NOT_STICKY
    }

    override fun onDestroy() {
        cancelPomodoroAlarm()
        scope.cancel()
        super.onDestroy()
    }

    private fun follow(session: TimerSession?) {
        if (session == null) {
            cancelPomodoroAlarm()
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return
        }
        if (session.id == shownSessionId) {
            return
        }

        shownSessionId = session.id
        startInForeground(notification(session))
        pomodoroJob?.cancel()
        cancelPomodoroAlarm()
        pomodoroJob = session.plannedEndAt?.let { end ->
            // The delay stops while the phone sleeps; the alarm wakes it at the end.
            armPomodoroAlarm(end)
            scope.launch {
                delay(Duration.between(clock.instant(), end).toMillis().coerceAtLeast(0))
                endPomodoroIfUp(session)
            }
        }
    }

    /** Says a Pomodoro is up once, whether the delay or the alarm gets there first. */
    private fun endPomodoroIfUp(session: TimerSession) {
        val end = session.plannedEndAt ?: return
        if (session.id == endedSessionId || clock.instant().isBefore(end)) {
            return
        }

        endedSessionId = session.id
        scope.launch {
            showPomodoroDone(session)
            tracker.finishPomodoro()
        }
    }

    private fun armPomodoroAlarm(end: Instant) {
        val alarmManager = getSystemService(AlarmManager::class.java)
        val canBeExact = Build.VERSION.SDK_INT < Build.VERSION_CODES.S || alarmManager.canScheduleExactAlarms()
        if (canBeExact) {
            alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, end.toEpochMilli(), pomodoroEndIntent())
        } else {
            alarmManager.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, end.toEpochMilli(), pomodoroEndIntent())
        }
    }

    private fun cancelPomodoroAlarm() {
        getSystemService(AlarmManager::class.java).cancel(pomodoroEndIntent())
    }

    private fun pomodoroEndIntent(): PendingIntent = PendingIntent.getForegroundService(
        this,
        0,
        Intent(this, TimerService::class.java).setAction(ACTION_POMODORO_END),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    private fun stopTimer() {
        scope.launch {
            val result = tracker.stop()
            if (result is ApiResult.Failure) {
                tracker.running.value?.let { startInForeground(notification(it, isStopFailed = true)) }
            }
        }
    }

    private fun startInForeground(notification: Notification) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(AppNotifications.TIMER_NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
        } else {
            startForeground(AppNotifications.TIMER_NOTIFICATION_ID, notification)
        }
    }

    private fun notification(session: TimerSession, isStopFailed: Boolean = false): Notification {
        val isPomodoro = session.mode == TimerMode.Pomodoro
        val text = when {
            isStopFailed -> getString(R.string.timer_stop_failed)
            isPomodoro -> getString(R.string.timer_notification_pomodoro, (session.plannedSeconds ?: 0) / 60)
            else -> getString(R.string.timer_notification_free)
        }

        return baseNotification()
            .setContentTitle(session.taskLabel ?: getString(R.string.timer_untitled))
            .setContentText(text)
            .setUsesChronometer(true)
            .setChronometerCountDown(isPomodoro)
            .setWhen((session.plannedEndAt ?: session.startedAt).toEpochMilli())
            .setShowWhen(true)
            .setContentIntent(AppNotifications.openTask(this, session.taskId))
            .addAction(R.drawable.ic_player_stop, getString(R.string.timer_stop), stopIntent())
            .build()
    }

    private fun placeholder(): Notification = baseNotification().setContentTitle(getString(R.string.timer_untitled)).build()

    private fun baseNotification(): NotificationCompat.Builder = NotificationCompat.Builder(this, AppNotifications.TIMER_CHANNEL)
        .setSmallIcon(R.drawable.ic_clock)
        .setOngoing(true)
        .setOnlyAlertOnce(true)
        .setCategory(NotificationCompat.CATEGORY_STOPWATCH)
        .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)

    /** A Pomodoro is up: say so, with the break from Settings counting down. */
    private suspend fun showPomodoroDone(session: TimerSession) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val breakMinutes = settingsStore.pomodoroBreakMinutes.first()
        val breakEnd = clock.instant().plus(Duration.ofMinutes(breakMinutes.toLong()))
        val notification = NotificationCompat.Builder(this, AppNotifications.TIMER_ALERTS_CHANNEL)
            .setSmallIcon(R.drawable.ic_clock)
            .setContentTitle(getString(R.string.timer_pomodoro_done))
            .setContentText(getString(R.string.timer_pomodoro_break, breakMinutes, session.taskLabel ?: getString(R.string.timer_untitled)))
            .setUsesChronometer(true)
            .setChronometerCountDown(true)
            .setWhen(breakEnd.toEpochMilli())
            .setShowWhen(true)
            .setTimeoutAfter(Duration.ofMinutes(breakMinutes.toLong()).toMillis())
            .setAutoCancel(true)
            .setContentIntent(AppNotifications.openTask(this, session.taskId))
            .build()

        NotificationManagerCompat.from(this).notify(AppNotifications.TIMER_DONE_NOTIFICATION_ID, notification)
    }

    private fun stopIntent(): PendingIntent = PendingIntent.getService(
        this,
        0,
        Intent(this, TimerService::class.java).setAction(ACTION_STOP),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    private companion object {
        const val ACTION_STOP = "com.minthantzintun.trypost.timer.STOP"
        const val ACTION_POMODORO_END = "com.minthantzintun.trypost.timer.POMODORO_END"
    }
}
