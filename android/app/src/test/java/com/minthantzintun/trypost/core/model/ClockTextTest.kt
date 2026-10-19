package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Test

class ClockTextTest {
    @Test
    fun `a length reads as minutes and seconds, with hours from an hour on`() {
        assertEquals("0:00", ClockText.format(0))
        assertEquals("4:05", ClockText.format(245))
        assertEquals("1:02:03", ClockText.format(3_723))
        assertEquals("0:00", ClockText.format(-5))
    }
}
