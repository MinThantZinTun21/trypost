package com.minthantzintun.trypost.core.download

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R

/**
 * TryPost's "Downloads" channel. It is LOW importance, so a download shows
 * an icon in the status bar without a sound, unlike the system Download
 * Manager's MIN channel, which many phones fold away.
 */
object DownloadNotifications {
    private const val CHANNEL_ID = "downloads"

    /** The ongoing progress notification, owned by DownloadProgressService. */
    const val PROGRESS_ID = 2001

    /** Completion notifications use this tag with the download id, so a second post replaces the first. */
    private const val COMPLETE_TAG = "download-complete"

    fun createChannel(context: Context) {
        val channel = NotificationChannel(
            CHANNEL_ID,
            context.getString(R.string.downloads_channel),
            NotificationManager.IMPORTANCE_LOW,
        )
        context.getSystemService(NotificationManager::class.java)?.createNotificationChannel(channel)
    }

    fun progress(context: Context, summary: DownloadSummary?): Notification {
        val title = when {
            summary == null -> context.getString(R.string.download_running)
            summary.fileName != null -> context.getString(R.string.download_progress_one, summary.fileName)
            else -> context.resources.getQuantityString(R.plurals.download_progress_many, summary.fileCount, summary.fileCount)
        }

        return NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_download)
            .setContentTitle(title)
            .setContentText(summary?.percent?.let { context.getString(R.string.download_percent, it) })
            .setProgress(100, summary?.percent ?: 0, summary?.percent == null)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setSilent(true)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build()
    }

    /** "Saved to Downloads" (a tap opens [file] when there is one) or "Download failed", for one finished download. */
    fun complete(context: Context, downloadId: Long, fileName: String, saved: Boolean, file: Uri?, mimeType: String?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val builder = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_download)
            .setContentText(fileName)
            .setAutoCancel(true)

        if (!saved) {
            builder.setContentTitle(context.getString(R.string.download_failed_title))
        } else if (file == null) {
            builder.setContentTitle(context.getString(R.string.download_saved))
        } else {
            val open = Intent(Intent.ACTION_VIEW)
                .setDataAndType(file, mimeType)
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
            builder
                .setContentTitle(context.getString(R.string.download_saved))
                .setContentIntent(PendingIntent.getActivity(context, downloadId.toInt(), open, PendingIntent.FLAG_IMMUTABLE))
        }

        NotificationManagerCompat.from(context).notify(COMPLETE_TAG, downloadId.toInt(), builder.build())
    }
}
