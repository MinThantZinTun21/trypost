package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.MediaItem
import com.minthantzintun.trypost.core.model.MediaType
import com.minthantzintun.trypost.core.model.Platform
import com.minthantzintun.trypost.core.model.PlatformStatus
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostPlatform
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.network.MediaDto
import com.minthantzintun.trypost.core.network.PostDto
import com.minthantzintun.trypost.core.network.PostPlatformDto
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull
import java.time.Instant
import java.time.OffsetDateTime
import java.time.format.DateTimeParseException

internal fun PostDto.toPost(): Post = Post(
    id = id,
    status = PostStatus.fromWire(status),
    content = content,
    scheduledAt = scheduledAt.toInstant(),
    publishedAt = publishedAt.toInstant(),
    createdAt = createdAt.toInstant(),
    isEditable = editable,
    media = media.map { it.toMediaItem() },
    platforms = platforms.map { it.toPostPlatform() },
)

private fun MediaDto.toMediaItem(): MediaItem = MediaItem(
    id = id,
    type = MediaType.classify(type, mimeType),
    url = url,
    originalFilename = originalFilename,
)

private fun PostPlatformDto.toPostPlatform(): PostPlatform = PostPlatform(
    id = id,
    platform = Platform.fromWire(platform),
    contentType = contentType,
    status = PlatformStatus.fromWire(status),
    meta = meta.mapValues { (_, value) -> value.toValue() },
    platformUrl = platformUrl,
    errorMessage = errorMessage,
    publishedAt = publishedAt.toInstant(),
    accountName = socialAccount.displayName,
    accountUsername = socialAccount.username,
    accountAvatarUrl = socialAccount.avatarUrl,
)

private fun String?.toInstant(): Instant? = this?.let {
    try {
        OffsetDateTime.parse(it).toInstant()
    } catch (exception: DateTimeParseException) {
        null
    }
}

private fun JsonElement.toValue(): Any? = when (this) {
    is JsonNull -> null
    is JsonPrimitive -> if (isString) content else booleanOrNull ?: longOrNull ?: doubleOrNull ?: content
    is JsonArray -> map { it.toValue() }
    is JsonObject -> mapValues { (_, value) -> value.toValue() }
}
