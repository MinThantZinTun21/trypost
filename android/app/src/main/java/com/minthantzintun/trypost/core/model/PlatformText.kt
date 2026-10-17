package com.minthantzintun.trypost.core.model

/** A text a Post platform's Content type publishes, stored under its `meta` key. */
enum class PlatformText(val metaKey: String) {
    Title("title"),
    Description("description"),
    Caption("caption"),
    Tags("tags"),
    ;

    companion object {
        fun fromMetaKey(key: String): PlatformText? = entries.firstOrNull { it.metaKey == key }
    }
}

/** This Post platform's value for [text]: tags as one comma separated line, the others as they are. */
fun PostPlatform.text(text: PlatformText): String = when (val value = meta[text.metaKey]) {
    null -> ""
    is List<*> -> value.filterNotNull().joinToString(", ")
    else -> value.toString()
}
