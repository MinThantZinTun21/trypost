package com.minthantzintun.trypost.core.download

import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

/**
 * DownloadManager's word that one of TryPost's downloads finished, sent even
 * when the app's process is gone. It posts "Saved to Downloads" or "Download
 * failed" and refreshes the tracker so the Download buttons come back.
 * DownloadManager only answers for this app's own downloads, so a forged
 * broadcast finds nothing.
 */
@AndroidEntryPoint
class DownloadCompleteReceiver : BroadcastReceiver() {
    @Inject
    lateinit var tracker: DownloadTracker

    override fun onReceive(context: Context, intent: Intent) {
        val id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1)
        val manager = context.getSystemService(DownloadManager::class.java)

        if (intent.action == DownloadManager.ACTION_DOWNLOAD_COMPLETE && id >= 0 && manager != null) {
            manager.query(DownloadManager.Query().setFilterById(id))?.use { cursor ->
                if (cursor.moveToFirst()) {
                    val status = cursor.getInt(cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS))
                    val fileName = cursor.getString(cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_TITLE)).orEmpty()
                    val saved = status == DownloadManager.STATUS_SUCCESSFUL

                    DownloadNotifications.complete(
                        context = context,
                        downloadId = id,
                        fileName = fileName,
                        file = if (saved) manager.getUriForDownloadedFile(id) else null,
                        mimeType = if (saved) manager.getMimeTypeForDownloadedFile(id) else null,
                    )
                }
            }
        }

        tracker.refresh()
    }
}
