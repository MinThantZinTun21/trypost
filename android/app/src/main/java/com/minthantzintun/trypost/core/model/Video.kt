package com.minthantzintun.trypost.core.model

import java.time.Instant

/** A Video (ADR 0013): a name and a video file, not a Post. */
data class Video(
    val id: String,
    val name: String,
    val size: Long?,
    val durationSeconds: Double?,
    val createdAt: Instant?,
)

data class VideoPage(
    val videos: List<Video>,
    val page: Int,
    val lastPage: Int,
)
