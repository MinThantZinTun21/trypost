package com.minthantzintun.trypost.core.reminders

import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.time.Clock
import java.time.Instant
import javax.inject.Inject
import javax.inject.Singleton

/** Sets and cancels the exact alarm of one reminder. */
interface AlarmScheduler {
    fun arm(reminder: UpcomingReminder, at: Instant)

    fun cancel(reminderId: String)
}

/** Shows and hides a reminder's notification. */
interface ReminderNotifier {
    fun show(reminder: UpcomingReminder)

    fun cancel(reminderId: String)
}

/**
 * The reminders on this phone (spec #130). The upcoming list comes from the
 * server after every Task change and on app start, and is kept in the
 * DataStore; alarms are re-armed from that copy, so they survive a reboot
 * with no connection. Snooze stays on the phone; Dismiss and Done reach the
 * server (a Dismiss made offline is sent on the next sync).
 */
@Singleton
class ReminderAlarms @Inject constructor(
    private val tasksRepository: TasksRepository,
    private val store: ReminderStore,
    private val scheduler: AlarmScheduler,
    private val notifier: ReminderNotifier,
    private val clock: Clock,
) {
    private val lock = Mutex()

    /** Sends waiting dismissals, reads the upcoming list and re-arms; with no connection it re-arms what it has. */
    suspend fun sync() {
        val pending = lock.withLock { store.read().pendingDismissals }
        val sent = pending.filterTo(mutableSetOf()) { id -> tasksRepository.dismissReminder(id).isDoneOnServer() }

        val upcoming = tasksRepository.upcomingReminders()
        lock.withLock {
            val state = store.read()
            val stillPending = state.pendingDismissals - sent
            val next = if (upcoming is ApiResult.Success) {
                state.copy(upcoming = upcoming.value.filterNot { it.id in stillPending }, pendingDismissals = stillPending)
            } else {
                state.copy(pendingDismissals = stillPending)
            }
            rearmLocked(next)
        }
    }

    /** Re-arms every alarm from the stored copy, e.g. after a reboot or a clock change. */
    suspend fun rearm() = lock.withLock { rearmLocked(store.read()) }

    /** The alarm of [reminderId] went off: show it, and set its next ring if it repeats. */
    suspend fun ring(reminderId: String) = lock.withLock {
        val state = store.read()
        val reminder = state.upcoming.firstOrNull { it.id == reminderId } ?: return@withLock
        notifier.show(reminder)
        rearmLocked(state.copy(rings = state.rings + (reminderId to ReminderPlan.rang(reminder, clock.instant()))))
    }

    /** Rings again in ten minutes; the server never hears of it. */
    suspend fun snooze(reminderId: String) = lock.withLock {
        val state = store.read()
        val reminder = state.upcoming.firstOrNull { it.id == reminderId } ?: return@withLock
        notifier.cancel(reminderId)
        rearmLocked(state.copy(rings = state.rings + (reminderId to ReminderPlan.snoozed(reminder, state.rings[reminderId], clock.instant()))))
    }

    /** Stops the reminder at once, and for good once the server takes it. */
    suspend fun dismiss(reminderId: String) {
        forget(reminderId, pendingDismissal = true)
        if (tasksRepository.dismissReminder(reminderId).isDoneOnServer()) {
            lock.withLock {
                val state = store.read()
                store.write(state.copy(pendingDismissals = state.pendingDismissals - reminderId))
            }
        }
    }

    /** Marks the reminder's Task Done, which ends all its reminders; offline it at least dismisses this one. */
    suspend fun done(reminderId: String) {
        val reminder = lock.withLock { store.read().upcoming.firstOrNull { it.id == reminderId } }
        forget(reminderId, pendingDismissal = false)
        if (reminder == null) {
            return
        }

        if (tasksRepository.setStatus(reminder.taskId, TaskStatus.Done) !is ApiResult.Success) {
            dismiss(reminderId)
        }
    }

    /** Signed out: every alarm and notification goes, with the stored copy. */
    suspend fun clear() = lock.withLock {
        val state = store.read()
        (state.armedIds + state.upcoming.map { it.id }).forEach { id ->
            scheduler.cancel(id)
            notifier.cancel(id)
        }
        store.write(ReminderState())
    }

    private suspend fun forget(reminderId: String, pendingDismissal: Boolean) = lock.withLock {
        val state = store.read()
        notifier.cancel(reminderId)
        rearmLocked(
            state.copy(
                upcoming = state.upcoming.filterNot { it.id == reminderId },
                pendingDismissals = if (pendingDismissal) state.pendingDismissals + reminderId else state.pendingDismissals,
            ),
        )
    }

    private suspend fun rearmLocked(state: ReminderState) {
        val rings = ReminderPlan.keptRings(state.upcoming, state.rings)
        val alarms = ReminderPlan.alarms(state.upcoming, rings, clock.instant())
        val upcomingIds = state.upcoming.map { it.id }.toSet()

        (state.armedIds - alarms.keys).forEach { id ->
            scheduler.cancel(id)
            if (id !in upcomingIds) {
                notifier.cancel(id)
            }
        }
        state.upcoming.forEach { reminder -> alarms[reminder.id]?.let { scheduler.arm(reminder, it) } }

        store.write(state.copy(rings = rings, armedIds = alarms.keys))
    }

    /** A 404 means the reminder or its Task is already gone, which is as good as dismissed. */
    private fun ApiResult<Unit>.isDoneOnServer(): Boolean =
        this is ApiResult.Success || (this is ApiResult.Failure && error == ApiError.NotFound)
}
