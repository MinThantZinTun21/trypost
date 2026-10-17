package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostTab
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

/** Posts that fall on one day in the phone's timezone; `day` is null for undated Posts. */
data class DaySection(
    val day: LocalDate?,
    val posts: List<Post>,
)

/** The time a Post is listed under: when it went out on Published, else when it is due. */
fun Post.listedAt(tab: PostTab): Instant? = when (tab) {
    PostTab.Published -> publishedAt ?: scheduledAt
    PostTab.Scheduled -> scheduledAt
    PostTab.Failed -> scheduledAt ?: publishedAt
}

/** Groups Posts by day, keeping the server's order. */
fun groupByDay(posts: List<Post>, tab: PostTab, zone: ZoneId): List<DaySection> {
    val sections = mutableListOf<DaySection>()

    for (post in posts) {
        val day = post.listedAt(tab)?.atZone(zone)?.toLocalDate()
        val last = sections.lastOrNull()

        if (last != null && last.day == day) {
            sections[sections.lastIndex] = last.copy(posts = last.posts + post)
        } else {
            sections += DaySection(day = day, posts = listOf(post))
        }
    }

    return sections
}
