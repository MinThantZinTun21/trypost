package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Test

class PlatformTextTest {
    private fun platform(contentType: String, meta: Map<String, Any?>) = PostPlatform(
        id = "pp1",
        platform = Platform.YouTube,
        contentType = contentType,
        status = PlatformStatus.Pending,
        meta = meta,
        platformUrl = null,
        errorMessage = null,
        publishedAt = null,
        accountName = "Channel",
        accountUsername = null,
        accountAvatarUrl = null,
    )

    @Test
    fun `each content type has its own texts`() {
        assertEquals(listOf(PlatformText.Caption), PlatformText.forContentType("tiktok_video"))
        assertEquals(listOf(PlatformText.Title, PlatformText.Description), PlatformText.forContentType("tiktok_photo"))
        assertEquals(
            listOf(PlatformText.Title, PlatformText.Description, PlatformText.Tags),
            PlatformText.forContentType("youtube_short"),
        )
        assertEquals(emptyList<PlatformText>(), PlatformText.forContentType("facebook_post"))
        assertEquals(emptyList<PlatformText>(), PlatformText.forContentType(null))
    }

    @Test
    fun `tags read as one comma separated line and missing texts are empty`() {
        val short = platform("youtube_short", mapOf("title" to "Hi", "tags" to listOf("a", "b")))

        assertEquals("Hi", short.text(PlatformText.Title))
        assertEquals("a, b", short.text(PlatformText.Tags))
        assertEquals("", short.text(PlatformText.Description))
    }
}
