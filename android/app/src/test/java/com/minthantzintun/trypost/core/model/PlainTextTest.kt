package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Test

class PlainTextTest {
    @Test
    fun `plain text is kept as it is`() {
        assertEquals("Line one\nLine two", "Line one\nLine two".toPlainText())
    }

    @Test
    fun `html from older posts loses its tags and entities`() {
        assertEquals("New drop & more\nSecond", "<p>New <strong>drop</strong> &amp; more</p><p>Second</p>".toPlainText())
    }
}
