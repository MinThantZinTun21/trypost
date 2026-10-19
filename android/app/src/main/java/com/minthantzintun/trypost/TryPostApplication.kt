package com.minthantzintun.trypost

import android.app.Application
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.data.TaskChange
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.di.ApplicationScope
import com.minthantzintun.trypost.core.notifications.AppNotifications
import com.minthantzintun.trypost.core.reminders.ReminderAlarms
import dagger.hilt.android.HiltAndroidApp
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.CoroutineStart
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltAndroidApp
class TryPostApplication : Application() {
    @Inject
    lateinit var sessionRepository: SessionRepository

    @Inject
    lateinit var tasksRepository: TasksRepository

    @Inject
    lateinit var reminderAlarms: ReminderAlarms

    @Inject
    @ApplicationScope
    lateinit var applicationScope: CoroutineScope

    override fun onCreate() {
        super.onCreate()
        AppNotifications.ensureChannels(this)
        followSession()
    }

    /**
     * Signed in, the reminders' alarms follow the server: read on start and
     * after every Task change. Signed out, this phone rings and shows nothing.
     */
    private fun followSession() {
        applicationScope.launch {
            sessionRepository.session.map { it != null }.distinctUntilChanged().collectLatest { isSignedIn ->
                if (!isSignedIn) {
                    reminderAlarms.clear()

                    return@collectLatest
                }

                coroutineScope {
                    // Subscribed before the first sync, so a Task change made during it still re-syncs.
                    launch(start = CoroutineStart.UNDISPATCHED) {
                        tasksRepository.changes.collect { change ->
                            if (change is TaskChange.Saved || change is TaskChange.Deleted) {
                                reminderAlarms.sync()
                            }
                        }
                    }
                    reminderAlarms.sync()
                }
            }
        }
    }
}
