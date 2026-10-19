package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.model.MediaItem
import com.minthantzintun.trypost.core.model.MediaType
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostStatus
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.Duration
import java.time.Instant

class PostCardsTest {
    private fun media(id: String, type: MediaType) = MediaItem(id = id, type = type, url = "https://cdn.example.com/$id", originalFilename = null, download = null)

    private fun post(vararg media: MediaItem) = Post(
        id = "p1",
        status = PostStatus.Scheduled,
        content = "",
        scheduledAt = null,
        publishedAt = null,
        createdAt = null,
        isEditable = true,
        media = media.toList(),
        platforms = emptyList(),
    )

    @Test
    fun `the thumbnail is the first image`() {
        val thumbnail = post(media("doc", MediaType.Document), media("a", MediaType.Image), media("b", MediaType.Image)).thumbnail()

        assertEquals(PostThumbnail.Image("https://cdn.example.com/a"), thumbnail)
    }

    @Test
    fun `a post that leads with a video shows a video mark`() {
        assertEquals(PostThumbnail.Video, post(media("v", MediaType.Video), media("a", MediaType.Image)).thumbnail())
    }

    @Test
    fun `a post without images or videos has no thumbnail`() {
        assertEquals(PostThumbnail.None, post().thumbnail())
        assertEquals(PostThumbnail.None, post(media("doc", MediaType.Document)).thumbnail())
    }

    @Test
    fun `a video after a document still shows the video mark`() {
        assertEquals(PostThumbnail.Video, post(media("doc", MediaType.Document), media("v", MediaType.Video)).thumbnail())
    }

    private val now = Instant.parse("2030-01-01T10:00:00Z")

    private fun until(duration: Duration) = timeUntil(now.plus(duration), now)

    @Test
    fun `under an hour counts minutes, rounding up`() {
        assertEquals(TimeUntil.Minutes(1), until(Duration.ofSeconds(10)))
        assertEquals(TimeUntil.Minutes(45), until(Duration.ofMinutes(45)))
        assertEquals(TimeUntil.Minutes(45), until(Duration.ofMinutes(44).plusSeconds(30)))
    }

    @Test
    fun `under a day counts hours and minutes`() {
        assertEquals(TimeUntil.Hours(1, 0), until(Duration.ofMinutes(60)))
        assertEquals(TimeUntil.Hours(2, 15), until(Duration.ofMinutes(135)))
        assertEquals(TimeUntil.Hours(23, 59), until(Duration.ofHours(24).minusMinutes(1)))
    }

    @Test
    fun `a day or more counts whole days`() {
        assertEquals(TimeUntil.Days(1), until(Duration.ofHours(24)))
        assertEquals(TimeUntil.Days(3), until(Duration.ofHours(80)))
    }

    @Test
    fun `a time that has come shows nothing`() {
        assertNull(timeUntil(now, now))
        assertNull(until(Duration.ofMinutes(-5)))
    }
}
