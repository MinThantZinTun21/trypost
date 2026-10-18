package com.minthantzintun.trypost.core.download

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/** How far one download has got. `totalBytes` is null while the size is unknown. */
data class DownloadProgress(
    val fileName: String,
    val bytesSoFar: Long,
    val totalBytes: Long?,
) {
    val fraction: Float? get() = totalBytes?.takeIf { it > 0 }?.let { (bytesSoFar.toFloat() / it).coerceIn(0f, 1f) }
    val percent: Int? get() = fraction?.let { (it * 100).toInt() }
}

/**
 * The progress of the app's unfinished downloads, keyed by their Download
 * link, for the Download buttons and the progress notification. The
 * notification's service refreshes it while anything runs; a Download press
 * refreshes it at once.
 */
@Singleton
class DownloadTracker @Inject constructor(
    private val rows: DownloadRows,
) {
    private val _progress = MutableStateFlow<Map<String, DownloadProgress>>(emptyMap())
    val progress: StateFlow<Map<String, DownloadProgress>> = _progress.asStateFlow()

    /** Reads DownloadManager again; true while anything is still downloading. */
    fun refresh(): Boolean {
        val unfinished = rows.unfinished()
        _progress.value = unfinished.associate { row ->
            row.url to DownloadProgress(row.fileName, row.bytesSoFar, row.totalBytes.takeIf { it >= 0 })
        }

        return unfinished.isNotEmpty()
    }
}
