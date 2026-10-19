package com.minthantzintun.trypost.feature.detail

import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.Instant
import java.time.ZoneId

class QuickPicksTest {
    private val yangon = ZoneId.of("Asia/Yangon") // UTC+06:30, no DST
    private val london = ZoneId.of("Europe/London")

    private fun picks(now: String, current: String?, zone: ZoneId = yangon): Map<QuickPickKind, Instant> =
        quickReschedulePicks(Instant.parse(now), current?.let(Instant::parse), zone).associate { it.kind to it.at }

    @Test
    fun `in the morning every pick is offered, in order`() {
        // 10:02:10 in Yangon; the Post is due at 15:00 Yangon today.
        val result = quickReschedulePicks(Instant.parse("2030-01-01T03:32:10Z"), Instant.parse("2030-01-01T08:30:00Z"), yangon)

        assertEquals(
            listOf(QuickPickKind.InOneHour, QuickPickKind.Tonight, QuickPickKind.TomorrowMorning, QuickPickKind.OneDayLater),
            result.map { it.kind },
        )
    }

    @Test
    fun `in one hour rounds up to the next five minutes`() {
        assertEquals(Instant.parse("2030-01-01T04:35:00Z"), picks("2030-01-01T03:32:10Z", null).getValue(QuickPickKind.InOneHour))
        assertEquals(Instant.parse("2030-01-01T04:30:00Z"), picks("2030-01-01T03:30:00Z", null).getValue(QuickPickKind.InOneHour))
    }

    @Test
    fun `tonight is 20 00 and tomorrow is 09 00 in the phone's zone`() {
        val result = picks("2030-01-01T03:32:10Z", null)

        assertEquals(Instant.parse("2030-01-01T13:30:00Z"), result.getValue(QuickPickKind.Tonight))
        assertEquals(Instant.parse("2030-01-02T02:30:00Z"), result.getValue(QuickPickKind.TomorrowMorning))
    }

    @Test
    fun `one day later keeps the post's own time`() {
        assertEquals(
            Instant.parse("2030-01-02T08:30:00Z"),
            picks("2030-01-01T03:32:10Z", "2030-01-01T08:30:00Z").getValue(QuickPickKind.OneDayLater),
        )
    }

    @Test
    fun `picks that are not in the future are dropped`() {
        // 21:00 in Yangon: tonight has passed; the Post's time was two days ago.
        val result = picks("2030-01-01T14:30:00Z", "2029-12-30T08:30:00Z")

        assertEquals(setOf(QuickPickKind.InOneHour, QuickPickKind.TomorrowMorning), result.keys)
    }

    @Test
    fun `without a scheduled time there is no one day later`() {
        assertEquals(false, QuickPickKind.OneDayLater in picks("2030-01-01T03:32:10Z", null))
    }

    @Test
    fun `local times survive a daylight saving change`() {
        // Saturday 30 March 2030, 12:00 GMT; British Summer Time starts that night.
        val result = picks("2030-03-30T12:00:00Z", "2030-03-30T18:00:00Z", london)

        assertEquals(Instant.parse("2030-03-31T08:00:00Z"), result.getValue(QuickPickKind.TomorrowMorning)) // 09:00 BST
        assertEquals(Instant.parse("2030-03-31T17:00:00Z"), result.getValue(QuickPickKind.OneDayLater)) // 18:00 BST
    }
}
