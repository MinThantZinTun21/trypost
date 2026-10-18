package com.minthantzintun.trypost.core.download

/** One of the app's DownloadManager downloads. `totalBytes` is -1 while the size is unknown. */
data class DownloadRow(
    val url: String,
    val fileName: String,
    val bytesSoFar: Long,
    val totalBytes: Long,
)

/** Reads the app's own downloads that are pending, running or paused. */
interface DownloadRows {
    fun unfinished(): List<DownloadRow>
}
