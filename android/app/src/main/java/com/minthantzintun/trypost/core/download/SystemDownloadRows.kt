package com.minthantzintun.trypost.core.download

import android.app.DownloadManager
import android.content.Context
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject

/** DownloadManager lists only the calling app's downloads, so this is TryPost's alone. */
class SystemDownloadRows @Inject constructor(
    @ApplicationContext private val context: Context,
) : DownloadRows {
    override fun unfinished(): List<DownloadRow> {
        val manager = context.getSystemService(DownloadManager::class.java) ?: return emptyList()
        val query = DownloadManager.Query().setFilterByStatus(
            DownloadManager.STATUS_PENDING or DownloadManager.STATUS_RUNNING or DownloadManager.STATUS_PAUSED,
        )

        return manager.query(query)?.use { cursor ->
            val uri = cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_URI)
            val title = cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_TITLE)
            val soFar = cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_BYTES_DOWNLOADED_SO_FAR)
            val total = cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_TOTAL_SIZE_BYTES)

            buildList {
                while (cursor.moveToNext()) {
                    add(
                        DownloadRow(
                            url = cursor.getString(uri),
                            fileName = cursor.getString(title).orEmpty(),
                            bytesSoFar = cursor.getLong(soFar),
                            totalBytes = cursor.getLong(total),
                        ),
                    )
                }
            }
        }.orEmpty()
    }
}
