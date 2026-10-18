package com.minthantzintun.trypost.core.download

import android.annotation.SuppressLint
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.IBinder
import androidx.core.app.NotificationManagerCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Keeps TryPost's progress notification current while DownloadManager does
 * the transfer, also with the app in the background. Once a second it
 * refreshes [DownloadTracker] (which the Download buttons show too) and stops
 * when nothing is left to download. The finished files get their own
 * notification from [DownloadCompleteReceiver].
 */
@AndroidEntryPoint
class DownloadProgressService : Service() {
    @Inject
    lateinit var tracker: DownloadTracker

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var watching: Job? = null

    /** The latest start, so the watcher only stops a service no new Download has restarted. */
    @Volatile
    private var lastStartId = 0

    override fun onBind(intent: Intent?): IBinder? = null

    @SuppressLint("InlinedApi") // ServiceCompat drops the type below Android 10.
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        lastStartId = startId
        ServiceCompat.startForeground(
            this,
            DownloadNotifications.PROGRESS_ID,
            DownloadNotifications.progress(this, DownloadSummary.of(tracker.progress.value.values)),
            ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC,
        )

        if (watching?.isActive != true) {
            watching = scope.launch { watch() }
        }

        return START_NOT_STICKY
    }

    private suspend fun watch() {
        val notifications = NotificationManagerCompat.from(this)

        while (scope.isActive) {
            val startId = lastStartId

            while (tracker.refresh()) {
                if (notifications.areNotificationsEnabled()) {
                    @Suppress("MissingPermission") // areNotificationsEnabled() is the check.
                    notifications.notify(
                        DownloadNotifications.PROGRESS_ID,
                        DownloadNotifications.progress(this, DownloadSummary.of(tracker.progress.value.values)),
                    )
                }
                delay(REFRESH_MS)
            }

            // A Download pressed since this pass began keeps the service, and the watcher, going.
            if (stopSelfResult(startId)) {
                return
            }
        }
    }

    /** Android 15+ caps a dataSync service; DownloadManager keeps going without it. */
    override fun onTimeout(startId: Int, fgsType: Int) {
        stopSelf()
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        private const val REFRESH_MS = 1_000L

        /** Starts watching; a phone that refuses a foreground service still downloads, just without progress. */
        fun start(context: Context) {
            try {
                ContextCompat.startForegroundService(context, Intent(context, DownloadProgressService::class.java))
            } catch (_: IllegalStateException) {
                // ForegroundServiceStartNotAllowedException: the completion notification still comes.
            }
        }
    }
}
