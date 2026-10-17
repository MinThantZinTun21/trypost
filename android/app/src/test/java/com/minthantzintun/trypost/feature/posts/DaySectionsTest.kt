package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.model.PostTab
import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

class DaySectionsTest {
    private fun post(id: String, scheduledAt: String?, publishedAt: String? = null) = Post(
        id = id,
        status = PostStatus.Scheduled,
        content = "",
        scheduledAt = scheduledAt?.let(Instant::parse),
        publishedAt = publishedAt?.let(Instant::parse),
        createdAt = null,
        isEditable = true,
        media = emptyList(),
        platforms = emptyList(),
    )

    @Test
    fun `posts are grouped by day in the phone's timezone`() {
        val yangon = ZoneId.of("Asia/Yangon")
        val posts = listOf(
            post("a", "2030-01-01T17:00:00Z"), // 23:30 on 1 Jan in Yangon
            post("b", "2030-01-01T18:00:00Z"), // 00:30 on 2 Jan in Yangon
            post("c", "2030-01-02T09:00:00Z"),
        )

        val sections = groupByDay(posts, PostTab.Scheduled, yangon)

        assertEquals(listOf(LocalDate.of(2030, 1, 1), LocalDate.of(2030, 1, 2)), sections.map { it.day })
        assertEquals(listOf(listOf("a"), listOf("b", "c")), sections.map { section -> section.posts.map { it.id } })
    }

    @Test
    fun `undated drafts share one section`() {
        val sections = groupByDay(listOf(post("a", null), post("b", null)), PostTab.Draft, ZoneId.of("UTC"))

        assertEquals(1, sections.size)
        assertEquals(null, sections.single().day)
    }

    @Test
    fun `published posts are listed by when they went out`() {
        val sections = groupByDay(
            listOf(post("a", "2030-01-01T09:00:00Z", publishedAt = "2030-01-03T09:00:00Z")),
            PostTab.Published,
            ZoneId.of("UTC"),
        )

        assertEquals(LocalDate.of(2030, 1, 3), sections.single().day)
    }
}
