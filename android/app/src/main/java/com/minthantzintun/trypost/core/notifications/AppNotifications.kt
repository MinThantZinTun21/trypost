package com.minthantzintun.trypost.core.notifications

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.net.toUri
import com.minthantzintun.trypost.MainActivity
import com.minthantzintun.trypost.R

/** The app's notification channels and the taps that open a Task. */
object AppNotifications {
    /** High importance: a reminder rings and shows over other apps. */
    const val REMINDERS_CHANNEL = "task_reminders"

    const val EXTRA_TASK_ID = "com.minthantzintun.trypost.TASK_ID"

    /** Set by a reminder's Open, which also dismisses that reminder. */
    const val EXTRA_DISMISS_REMINDER_ID = "com.minthantzintun.trypost.DISMISS_REMINDER_ID"

    /** The timer's channels, gone with the timer; an updated install drops them too. */
    private val removedChannels = listOf("task_timer", "task_timer_alerts")

    fun ensureChannels(context: Context) {
        val manager = context.getSystemService(NotificationManager::class.java)
        manager.createNotificationChannel(
            NotificationChannel(REMINDERS_CHANNEL, context.getString(R.string.channel_reminders), NotificationManager.IMPORTANCE_HIGH),
        )
        removedChannels.forEach { manager.deleteNotificationChannel(it) }
    }

    /**
     * Opens Task [taskId] in the running app (or starts it). [dismissReminderId]
     * also dismisses that reminder, since the Owner has seen it.
     */
    fun openTask(context: Context, taskId: String, dismissReminderId: String? = null): PendingIntent {
        val intent = Intent(context, MainActivity::class.java)
            .setAction(Intent.ACTION_VIEW)
            // The data keeps one PendingIntent per Task and reminder apart.
            .setData("trypost://tasks/$taskId?reminder=${dismissReminderId.orEmpty()}".toUri())
            .putExtra(EXTRA_TASK_ID, taskId)
            .putExtra(EXTRA_DISMISS_REMINDER_ID, dismissReminderId)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)

        return PendingIntent.getActivity(context, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }
}
