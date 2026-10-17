package com.minthantzintun.trypost.core.model

/** A text a Post platform's Content type publishes, stored under its `meta` key. */
enum class PlatformText(val metaKey: String) {
    Title("title"),
    Description("description"),
    Caption("caption"),
    Tags("tags"),
    ;

    companion object {
        /**
         * The texts each Content type publishes; a Facebook post publishes
         * only the Post content, so it has none of its own.
         */
        fun forContentType(contentType: String?): List<PlatformText> = when (contentType) {
            "tiktok_video" -> listOf(Caption)
            "tiktok_photo" -> listOf(Title, Description)
            "youtube_short" -> listOf(Title, Description, Tags)
            else -> emptyList()
        }
    }
}

/** This Post platform's value for [text]: tags as a list, the others as a string. */
fun PostPlatform.text(text: PlatformText): String = when (val value = meta[text.metaKey]) {
    null -> ""
    is List<*> -> value.filterNotNull().joinToString(", ")
    else -> value.toString()
}
