package com.minthantzintun.trypost.core.reminders

import android.Manifest
import android.app.AlarmManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.text.format.DateFormat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.notifications.AppNotifications
import dagger.hilt.android.qualifiers.ApplicationContext
import java.time.Instant
import java.util.Date
import javax.inject.Inject
import javax.inject.Singleton

/** A reminder's alarm through [AlarmManager.setAlarmClock], one PendingIntent per reminder, never a repeating alarm. */
@Singleton
class SystemAlarmScheduler @Inject constructor(
    @ApplicationContext private val context: Context,
) : AlarmScheduler {
    private val alarmManager = context.getSystemService(AlarmManager::class.java)

    override fun arm(reminder: UpcomingReminder, at: Instant) {
        val ring = ReminderReceiver.pendingIntent(context, ReminderReceiver.ACTION_RING, reminder.id)
        val canBeExact = Build.VERSION.SDK_INT < Build.VERSION_CODES.S || alarmManager.canScheduleExactAlarms()

        if (canBeExact) {
            val show = AppNotifications.openTask(context, reminder.taskId)
            alarmManager.setAlarmClock(AlarmManager.AlarmClockInfo(at.toEpochMilli(), show), ring)
        } else {
            // Without the exact alarm permission it still rings, within a few minutes.
            alarmManager.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, at.toEpochMilli(), ring)
        }
    }

    override fun cancel(reminderId: String) {
        alarmManager.cancel(ReminderReceiver.pendingIntent(context, ReminderReceiver.ACTION_RING, reminderId))
    }
}

/** A reminder on the high-importance "Task reminders" channel with Done, Snooze 10 min and Open. */
@Singleton
class SystemReminderNotifier @Inject constructor(
    @ApplicationContext private val context: Context,
) : ReminderNotifier {
    override fun show(reminder: UpcomingReminder): Boolean {
        val isAllowed = NotificationManagerCompat.from(context).areNotificationsEnabled() &&
            (
                Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
                    ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
                )
        if (!isAllowed) {
            return false
        }

        val open = AppNotifications.openTask(context, reminder.taskId, dismissReminderId = reminder.id)
        val time = DateFormat.getTimeFormat(context).format(Date(reminder.remindAtMillis))
        val notification = NotificationCompat.Builder(context, AppNotifications.REMINDERS_CHANNEL)
            .setSmallIcon(R.drawable.ic_bell)
            .setContentTitle(reminder.taskLabel ?: context.getString(R.string.reminder_untitled))
            .setContentText(context.getString(R.string.reminder_notification_text, time))
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setWhen(reminder.remindAtMillis)
            .setShowWhen(true)
            .setContentIntent(open)
            .setDeleteIntent(ReminderReceiver.pendingIntent(context, ReminderReceiver.ACTION_DISMISS, reminder.id))
            .setAutoCancel(true)
            .addAction(R.drawable.ic_circle_check, context.getString(R.string.reminder_done), ReminderReceiver.pendingIntent(context, ReminderReceiver.ACTION_DONE, reminder.id))
            .addAction(R.drawable.ic_clock, context.getString(R.string.reminder_snooze), ReminderReceiver.pendingIntent(context, ReminderReceiver.ACTION_SNOOZE, reminder.id))
            .addAction(R.drawable.ic_external, context.getString(R.string.reminder_open), open)
            .build()

        NotificationManagerCompat.from(context).notify(notificationId(reminder.id), notification)

        return true
    }

    override fun cancel(reminderId: String) {
        NotificationManagerCompat.from(context).cancel(notificationId(reminderId))
    }

    private fun notificationId(reminderId: String): Int = reminderId.hashCode()
}
