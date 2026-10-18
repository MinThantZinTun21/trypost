package com.minthantzintun.trypost.core.download

import com.minthantzintun.trypost.testing.FakeDownloadRows
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DownloadTrackerTest {
    private val rows = FakeDownloadRows()
    private val tracker = DownloadTracker(rows)

    @Test
    fun `unfinished downloads are tracked by their url with their progress`() {
        rows.rows = listOf(
            DownloadRow(url = "https://app.example.com/a", fileName = "a.mp4", bytesSoFar = 42, totalBytes = 100),
            DownloadRow(url = "https://app.example.com/b", fileName = "b.jpg", bytesSoFar = 0, totalBytes = -1),
        )

        assertTrue(tracker.refresh())

        val a = tracker.progress.value.getValue("https://app.example.com/a")
        assertEquals("a.mp4", a.fileName)
        assertEquals(42, a.percent)
        assertEquals(0.42f, a.fraction!!, 0.0001f)

        val b = tracker.progress.value.getValue("https://app.example.com/b")
        assertNull(b.totalBytes)
        assertNull(b.percent)
        assertNull(b.fraction)
    }

    @Test
    fun `a finished download leaves the tracker`() {
        rows.rows = listOf(DownloadRow(url = "https://app.example.com/a", fileName = "a.mp4", bytesSoFar = 99, totalBytes = 100))
        tracker.refresh()

        rows.rows = emptyList()

        assertFalse(tracker.refresh())
        assertEquals(emptyMap<String, DownloadProgress>(), tracker.progress.value)
    }
}
