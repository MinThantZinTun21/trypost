package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.model.MediaType
import com.minthantzintun.trypost.core.model.Post
import java.time.Duration
import java.time.Instant

/** What a Post's card shows at its start. The server has no video frames, so a video is a mark. */
sealed interface PostThumbnail {
    data class Image(val url: String) : PostThumbnail

    data object Video : PostThumbnail

    data object None : PostThumbnail
}

/** The first image, unless a video comes first; documents are skipped. */
fun Post.thumbnail(): PostThumbnail {
    val first = media.firstOrNull { it.type == MediaType.Image || it.type == MediaType.Video } ?: return PostThumbnail.None

    return if (first.type == MediaType.Video) PostThumbnail.Video else PostThumbnail.Image(first.url)
}

/** How long until a Scheduled post goes out, coarsening as it gets further away. */
sealed interface TimeUntil {
    data class Minutes(val minutes: Long) : TimeUntil

    data class Hours(val hours: Long, val minutes: Long) : TimeUntil

    data class Days(val days: Long) : TimeUntil
}

/** `null` once [target] is not in the future. Minutes round up, so "in 1 min" never shows a time that has passed. */
fun timeUntil(target: Instant, now: Instant): TimeUntil? {
    val seconds = Duration.between(now, target).seconds
    if (seconds <= 0) {
        return null
    }

    val minutes = (seconds + 59) / 60

    return when {
        minutes < 60 -> TimeUntil.Minutes(minutes)
        minutes < 24 * 60 -> TimeUntil.Hours(minutes / 60, minutes % 60)
        else -> TimeUntil.Days(minutes / (24 * 60))
    }
}
