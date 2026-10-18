package com.minthantzintun.trypost.core.model

/** A Post's status, as the server names it. */
enum class PostStatus(val wire: String) {
    Scheduled("scheduled"),
    Publishing("publishing"),
    Published("published"),
    PartiallyPublished("partially_published"),
    Failed("failed"),
    ;

    companion object {
        /** An unknown status reads as Publishing: it offers no action, and the server decides what is editable. */
        fun fromWire(value: String): PostStatus = entries.firstOrNull { it.wire == value } ?: Publishing
    }
}

/** A Post platform's own publishing status. */
enum class PlatformStatus(val wire: String) {
    Pending("pending"),
    Publishing("publishing"),
    Retrying("retrying"),
    Published("published"),
    Failed("failed"),
    ;

    companion object {
        fun fromWire(value: String): PlatformStatus = entries.firstOrNull { it.wire == value } ?: Pending
    }
}

enum class Platform(val wire: String, val label: String) {
    Facebook("facebook", "Facebook"),
    TikTok("tiktok", "TikTok"),
    YouTube("youtube", "YouTube"),
    ;

    companion object {
        fun fromWire(value: String): Platform = entries.firstOrNull { it.wire == value } ?: Facebook
    }
}

enum class MediaType {
    Image,
    Video,
    Document,
    ;

    companion object {
        /** Trusts the explicit type first, then the MIME, like the web app. */
        fun classify(type: String?, mimeType: String?): MediaType = when {
            type == "video" -> Video
            type == "document" -> Document
            type == "image" -> Image
            mimeType?.startsWith("video/") == true -> Video
            mimeType == "application/pdf" -> Document
            else -> Image
        }
    }
}

/**
 * The post list tabs. A tab is not a Post status: Scheduled also shows Posts
 * going out now, and Failed also shows Partially published ones.
 */
enum class PostTab(val wire: String) {
    Scheduled("scheduled"),
    Published("published"),
    Failed("failed"),
}
