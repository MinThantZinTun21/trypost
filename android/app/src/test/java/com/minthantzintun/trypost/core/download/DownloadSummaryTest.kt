package com.minthantzintun.trypost.core.download

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class DownloadSummaryTest {
    @Test
    fun `nothing downloading has no summary`() {
        assertNull(DownloadSummary.of(emptyList()))
    }

    @Test
    fun `one file is named with its own progress`() {
        val summary = DownloadSummary.of(listOf(DownloadProgress("tiger.mp4", bytesSoFar = 25, totalBytes = 100)))

        assertEquals(DownloadSummary(fileCount = 1, fileName = "tiger.mp4", percent = 25), summary)
    }

    @Test
    fun `several files are counted with their combined progress`() {
        val summary = DownloadSummary.of(
            listOf(
                DownloadProgress("a.mp4", bytesSoFar = 50, totalBytes = 100),
                DownloadProgress("b.mp4", bytesSoFar = 250, totalBytes = 300),
            ),
        )

        assertEquals(DownloadSummary(fileCount = 2, fileName = null, percent = 75), summary)
    }

    @Test
    fun `an unknown size makes the progress indeterminate`() {
        val summary = DownloadSummary.of(
            listOf(
                DownloadProgress("a.mp4", bytesSoFar = 50, totalBytes = 100),
                DownloadProgress("b.mp4", bytesSoFar = 10, totalBytes = null),
            ),
        )

        assertEquals(DownloadSummary(fileCount = 2, fileName = null, percent = null), summary)
    }
}
