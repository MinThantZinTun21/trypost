package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ValidationKeysTest {
    private fun platform(id: String, accountName: String) = PostPlatform(
        id = id,
        platform = Platform.YouTube,
        contentType = "youtube_short",
        status = PlatformStatus.Pending,
        meta = emptyMap(),
        texts = listOf(PlatformText.Title, PlatformText.Description, PlatformText.Tags),
        platformUrl = null,
        errorMessage = null,
        publishedAt = null,
        accountName = accountName,
        accountUsername = null,
        accountAvatarUrl = null,
    )

    private val post = Post(
        id = "p1",
        status = PostStatus.Draft,
        content = "",
        scheduledAt = null,
        publishedAt = null,
        createdAt = null,
        isEditable = true,
        media = emptyList(),
        platforms = listOf(platform("pp1", "Page"), platform("pp2", "Channel")),
    )

    @Test
    fun `a platforms key names the post platform at that index`() {
        assertEquals("pp2", post.platformForErrorKey("platforms.1.meta.title")?.id)
        assertEquals("pp1", post.platformForErrorKey("platforms.0.content_type")?.id)
        assertNull(post.platformForErrorKey("platforms.2.meta.title"))
        assertNull(post.platformForErrorKey("content"))
    }

    @Test
    fun `a meta key names its text, tags included`() {
        assertEquals(PlatformText.Title, platformTextForErrorKey("platforms.0.meta.title"))
        assertEquals(PlatformText.Tags, platformTextForErrorKey("platforms.1.meta.tags.3"))
        assertNull(platformTextForErrorKey("platforms.0.meta.privacy_level"))
        assertNull(platformTextForErrorKey("platforms.0.content_type"))
        assertNull(platformTextForErrorKey("content"))
    }
}
