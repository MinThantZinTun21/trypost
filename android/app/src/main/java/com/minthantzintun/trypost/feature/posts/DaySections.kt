package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.ui.DaySection
import com.minthantzintun.trypost.core.ui.groupByDay
import java.time.Instant
import java.time.ZoneId

/** The time a Post is listed under: when it went out on Published, else when it is due. */
fun Post.listedAt(tab: PostTab): Instant? = when (tab) {
    PostTab.Published -> publishedAt ?: scheduledAt
    PostTab.Scheduled -> scheduledAt
    PostTab.Failed -> scheduledAt ?: publishedAt
}

/** Groups Posts by the day they are listed under in [zone], keeping the server's order. */
fun groupByDay(posts: List<Post>, tab: PostTab, zone: ZoneId): List<DaySection<Post>> =
    groupByDay(posts) { it.listedAt(tab)?.atZone(zone)?.toLocalDate() }
