package com.minthantzintun.trypost.core.model

private val PlatformKey = Regex("""^platforms\.(\d+)\.(.+)$""")
private val MetaKey = Regex("""^meta\.([a-z_]+)(?:\.\d+)?$""")

/**
 * The Post platform a `platforms.N.…` validation key is about: the server
 * lists a Post's platforms in the order its checks number them.
 */
fun Post.platformForErrorKey(key: String): PostPlatform? =
    PlatformKey.matchEntire(key)?.groupValues?.get(1)?.toIntOrNull()?.let { platforms.getOrNull(it) }

/** The text a `platforms.N.meta.<text>` (or `….tags.M`) validation key is about. */
fun platformTextForErrorKey(key: String): PlatformText? =
    PlatformKey.matchEntire(key)
        ?.let { MetaKey.matchEntire(it.groupValues[2]) }
        ?.let { PlatformText.fromMetaKey(it.groupValues[1]) }
