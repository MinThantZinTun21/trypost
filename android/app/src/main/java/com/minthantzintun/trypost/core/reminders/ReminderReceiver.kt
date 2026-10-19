package com.minthantzintun.trypost.core.reminders

import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import androidx.core.net.toUri
import com.minthantzintun.trypost.core.di.ApplicationScope
import dagger.hilt.EntryPoint
import dagger.hilt.InstallIn
import dagger.hilt.android.EntryPointAccessors
import dagger.hilt.components.SingletonComponent
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch

/** A reminder's alarm, and its notification's Done, Snooze and swipe-away (Dismiss). */
class ReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val reminderId = intent.data?.lastPathSegment ?: return
        val (alarms, scope) = context.reminderDependencies()
        val pending = goAsync()

        scope.launch {
            try {
                when (intent.action) {
                    ACTION_RING -> alarms.ring(reminderId)
                    ACTION_SNOOZE -> alarms.snooze(reminderId)
                    ACTION_DONE -> alarms.done(reminderId)
                    ACTION_DISMISS -> alarms.dismiss(reminderId)
                }
            } finally {
                pending.finish()
            }
        }
    }

    companion object {
        const val ACTION_RING = "com.minthantzintun.trypost.reminders.RING"
        const val ACTION_SNOOZE = "com.minthantzintun.trypost.reminders.SNOOZE"
        const val ACTION_DONE = "com.minthantzintun.trypost.reminders.DONE"
        const val ACTION_DISMISS = "com.minthantzintun.trypost.reminders.DISMISS"

        /** One PendingIntent per reminder and action: the reminder's id is the intent's data. */
        fun pendingIntent(context: Context, action: String, reminderId: String): PendingIntent {
            val intent = Intent(context, ReminderReceiver::class.java)
                .setAction(action)
                .setData("trypost://reminders/$reminderId".toUri())

            return PendingIntent.getBroadcast(context, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        }
    }
}

/** Re-arms every reminder after a reboot, a clock or timezone change, or an app update, then syncs. */
class ReminderRearmReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action !in RearmActions) {
            return
        }
        val (alarms, scope) = context.reminderDependencies()
        val pending = goAsync()

        scope.launch {
            try {
                alarms.rearm()
                alarms.sync()
            } finally {
                pending.finish()
            }
        }
    }

    private companion object {
        val RearmActions = setOf(
            Intent.ACTION_BOOT_COMPLETED,
            Intent.ACTION_TIME_CHANGED,
            Intent.ACTION_TIMEZONE_CHANGED,
            Intent.ACTION_MY_PACKAGE_REPLACED,
        )
    }
}

/** What the receivers need from the app's graph; a receiver is not injected. */
@EntryPoint
@InstallIn(SingletonComponent::class)
interface ReminderReceiverEntryPoint {
    fun reminderAlarms(): ReminderAlarms

    @ApplicationScope
    fun applicationScope(): CoroutineScope
}

private fun Context.reminderDependencies(): Pair<ReminderAlarms, CoroutineScope> {
    val entryPoint = EntryPointAccessors.fromApplication(applicationContext, ReminderReceiverEntryPoint::class.java)

    return entryPoint.reminderAlarms() to entryPoint.applicationScope()
}
