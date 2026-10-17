package com.minthantzintun.trypost.core.model

import java.time.Instant

data class Post(
    val id: String,
    val status: PostStatus,
    val content: String,
    val scheduledAt: Instant?,
    val publishedAt: Instant?,
    val createdAt: Instant?,
    val isEditable: Boolean,
    val media: List<MediaItem>,
    val platforms: List<PostPlatform>,
)

data class MediaItem(
    val id: String,
    val type: MediaType,
    val url: String,
    val originalFilename: String?,
)

/** One Social account's part of a Post. */
data class PostPlatform(
    val id: String,
    val platform: Platform,
    val contentType: String?,
    val status: PlatformStatus,
    val meta: Map<String, Any?>,
    /** The texts this Post platform's Content type publishes, as the server lists them. */
    val texts: List<PlatformText>,
    val platformUrl: String?,
    val errorMessage: String?,
    val publishedAt: Instant?,
    val accountName: String,
    val accountUsername: String?,
    val accountAvatarUrl: String?,
)

data class PostPage(
    val posts: List<Post>,
    val page: Int,
    val lastPage: Int,
) {
    val hasMore: Boolean get() = page < lastPage
}
