package com.minthantzintun.trypost.core.timer

import com.minthantzintun.trypost.core.model.TimerClock
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.model.TimerSession
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant

class TimerClockTest {
    private val startedAt = Instant.parse("2030-01-01T09:00:00Z")

    private fun at(seconds: Long): Instant = startedAt.plusSeconds(seconds)

    @Test
    fun `the elapsed time is worked out from the start, whenever it is asked`() {
        assertEquals(0, TimerClock.elapsedSeconds(startedAt, startedAt, plannedSeconds = null))
        assertEquals(125, TimerClock.elapsedSeconds(startedAt, at(125), plannedSeconds = null))
        // The app was closed for an hour; nothing counted, yet the time is right.
        assertEquals(3_725, TimerClock.elapsedSeconds(startedAt, at(3_725), plannedSeconds = null))
    }

    @Test
    fun `a clock behind the start reads zero, and a Pomodoro never runs past its length`() {
        assertEquals(0, TimerClock.elapsedSeconds(startedAt, at(-30), plannedSeconds = null))
        assertEquals(1_500, TimerClock.elapsedSeconds(startedAt, at(4_000), plannedSeconds = 1_500))
        assertEquals(300L, TimerClock.remainingSeconds(startedAt, at(1_200), plannedSeconds = 1_500))
        assertEquals(0L, TimerClock.remainingSeconds(startedAt, at(4_000), plannedSeconds = 1_500))
        assertNull(TimerClock.remainingSeconds(startedAt, at(10), plannedSeconds = null))
    }

    @Test
    fun `a session runs until it is stopped, or until its Pomodoro is up`() {
        val pomodoro = TimerSession("s1", "t1", TimerMode.Pomodoro, startedAt, endedAt = null, plannedSeconds = 1_500, outcome = null)
        val stopped = TimerSession("s2", "t1", TimerMode.Free, startedAt, endedAt = at(90), plannedSeconds = null, outcome = null)

        assertTrue(pomodoro.isRunning(at(1_499)))
        assertFalse(pomodoro.isRunning(at(1_500)))
        assertFalse(stopped.isRunning(at(30)))
        assertEquals(90, stopped.elapsedSeconds(at(5_000)))
    }

    @Test
    fun `times read as minutes and seconds, with hours once there are any`() {
        assertEquals("0:00", TimerClock.format(0))
        assertEquals("4:05", TimerClock.format(245))
        assertEquals("1:02:03", TimerClock.format(3_723))
        assertEquals("0:00", TimerClock.format(-5))
    }
}
