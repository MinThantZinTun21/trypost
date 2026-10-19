package com.minthantzintun.trypost.core.timer

import android.content.Context
import android.content.Intent
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.core.data.TaskChange
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.di.ApplicationScope
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TimerSession
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.Clock
import javax.inject.Inject
import javax.inject.Singleton

/**
 * The Timer session running now (spec #130), from the server on app start
 * and from every start and stop. While one runs, [TimerService] keeps its
 * notification up; the time shown is always worked out from its start.
 */
@Singleton
class TimerTracker @Inject constructor(
    private val serviceLauncher: TimerServiceLauncher,
    private val tasksRepository: TasksRepository,
    @ApplicationScope private val scope: CoroutineScope,
    private val clock: Clock,
) {
    private val _running = MutableStateFlow<TimerSession?>(null)

    val running: StateFlow<TimerSession?> = _running.asStateFlow()

    init {
        scope.launch {
            tasksRepository.changes.collect { change ->
                when (change) {
                    is TaskChange.TimerChanged -> show(change.running)
                    // Done stops the Task's timer on the server, and a deleted Task takes its sessions along.
                    is TaskChange.Saved -> if (change.task.status == TaskStatus.Done && _running.value?.taskId == change.task.id) {
                        show(null)
                    }
                    is TaskChange.Deleted -> if (_running.value?.taskId == change.id) {
                        show(null)
                    }
                    TaskChange.TaskTagsChanged -> Unit
                }
            }
        }
    }

    /** Reads the running timer from the server; call it while the app is in front, since it may start the service. */
    suspend fun refresh() {
        val result = tasksRepository.runningTimer()
        if (result is ApiResult.Success) {
            show(result.value)
        }
    }

    /** Stops the running timer on the server; the service goes once the server confirms. */
    suspend fun stop(): ApiResult<TimerSession?>? = _running.value?.let { tasksRepository.stopTimer(it) }

    /** A Pomodoro ran its length: the server completes it when it is next read. */
    suspend fun finishPomodoro() {
        _running.value = null
        refresh()
    }

    /** Signed out: the timer stays on the server, but this phone stops showing it. */
    fun forget() {
        show(null)
    }

    private fun show(session: TimerSession?) {
        val running = session?.takeIf { it.isRunning(clock.instant()) }
        _running.value = running
        if (running != null) {
            serviceLauncher.launch()
        }
    }
}

/** Starts [TimerService]; tests leave it out. */
fun interface TimerServiceLauncher {
    fun launch()
}

/** Starts the service from the app's context. The system refuses it from the background, which the next start in front makes up for. */
class SystemTimerServiceLauncher @Inject constructor(@ApplicationContext private val context: Context) : TimerServiceLauncher {
    override fun launch() {
        runCatching { ContextCompat.startForegroundService(context, Intent(context, TimerService::class.java)) }
    }
}
