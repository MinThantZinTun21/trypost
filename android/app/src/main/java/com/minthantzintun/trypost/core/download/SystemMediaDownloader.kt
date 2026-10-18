package com.minthantzintun.trypost.core.download

import android.app.DownloadManager
import android.content.Context
import android.os.Environment
import androidx.core.net.toUri
import com.minthantzintun.trypost.core.model.DownloadLink
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject

/**
 * Hands the file to Android's DownloadManager, which keeps going when the app
 * closes, and starts TryPost's progress notification. Android 8–9 need
 * WRITE_EXTERNAL_STORAGE for Downloads, which the screen asks for first.
 */
class SystemMediaDownloader @Inject constructor(
    @ApplicationContext private val context: Context,
) : MediaDownloader {
    override fun start(link: DownloadLink): DownloadOutcome {
        val manager = context.getSystemService(DownloadManager::class.java) ?: return DownloadOutcome.Failed
        val request = DownloadManager.Request(link.url.toUri())
            .setTitle(link.fileName)
            // TryPost shows its own progress and completion (DownloadProgressService, DownloadCompleteReceiver).
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_HIDDEN)

        link.mimeType?.let { request.setMimeType(it) }

        return try {
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, link.fileName.safeFileName())
            manager.enqueue(request)
            DownloadProgressService.start(context)
            DownloadOutcome.Started
        } catch (_: IllegalStateException) {
            DownloadOutcome.Failed
        } catch (_: IllegalArgumentException) {
            DownloadOutcome.Failed
        } catch (_: SecurityException) {
            DownloadOutcome.Failed
        }
    }

    private fun String.safeFileName(): String = replace('/', '-').replace('\\', '-').ifBlank { "download" }
}
