package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class PlatformTextTest {
    private fun platform(meta: Map<String, Any?>) = PostPlatform(
        id = "pp1",
        platform = Platform.YouTube,
        contentType = "youtube_short",
        status = PlatformStatus.Pending,
        meta = meta,
        texts = listOf(PlatformText.Title, PlatformText.Description, PlatformText.Tags),
        platformUrl = null,
        errorMessage = null,
        publishedAt = null,
        accountName = "Channel",
        accountUsername = null,
        accountAvatarUrl = null,
    )

    @Test
    fun `texts come from their meta keys`() {
        assertEquals(PlatformText.Caption, PlatformText.fromMetaKey("caption"))
        assertEquals(PlatformText.Tags, PlatformText.fromMetaKey("tags"))
        assertNull(PlatformText.fromMetaKey("privacy_level"))
    }

    @Test
    fun `tags read as one comma separated line and missing texts are empty`() {
        val short = platform(mapOf("title" to "Hi", "tags" to listOf("a", "b")))

        assertEquals("Hi", short.text(PlatformText.Title))
        assertEquals("a, b", short.text(PlatformText.Tags))
        assertEquals("", short.text(PlatformText.Description))
    }

    @Test
    fun `the shown texts are the non blank ones in the Content type's order, ready to copy`() {
        val short = platform(mapOf("tags" to listOf("a", "b"), "title" to "Hi", "description" to "  ", "caption" to "Not published"))

        assertEquals(listOf(PlatformText.Title to "Hi", PlatformText.Tags to "a, b"), short.shownTexts())
    }
}
