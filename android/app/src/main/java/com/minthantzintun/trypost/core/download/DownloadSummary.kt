package com.minthantzintun.trypost.core.download

/**
 * What the ongoing notification says: the file's name when only one is
 * downloading, else how many, and their combined percent (null while any
 * size is unknown).
 */
data class DownloadSummary(
    val fileCount: Int,
    val fileName: String?,
    val percent: Int?,
) {
    companion object {
        fun of(downloads: Collection<DownloadProgress>): DownloadSummary? {
            if (downloads.isEmpty()) {
                return null
            }

            val totals = downloads.map { it.totalBytes }
            val percent = if (totals.any { it == null || it <= 0 }) {
                null
            } else {
                (downloads.sumOf { it.bytesSoFar } * 100 / totals.sumOf { it!! }).toInt().coerceIn(0, 100)
            }

            return DownloadSummary(
                fileCount = downloads.size,
                fileName = downloads.singleOrNull()?.fileName,
                percent = percent,
            )
        }
    }
}
