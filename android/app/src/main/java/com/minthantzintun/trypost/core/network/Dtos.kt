package com.minthantzintun.trypost.core.network

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

/*
 * The `/mobile/v1` wire format (`App\Http\Resources\Mobile\V1`). Times are
 * ISO 8601 strings in UTC.
 */

@Serializable
data class OwnerDto(val name: String)

@Serializable
data class PageDto<T>(
    val data: List<T>,
    val meta: PageMetaDto,
)

@Serializable
data class PageMetaDto(
    @SerialName("current_page") val currentPage: Int,
    @SerialName("last_page") val lastPage: Int,
)

@Serializable
data class PostDto(
    val id: String,
    val status: String,
    val content: String = "",
    @SerialName("scheduled_at") val scheduledAt: String? = null,
    @SerialName("published_at") val publishedAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    val editable: Boolean = false,
    val media: List<MediaDto> = emptyList(),
    val platforms: List<PostPlatformDto> = emptyList(),
)

@Serializable
data class MediaDto(
    val id: String,
    val type: String? = null,
    val url: String,
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("original_filename") val originalFilename: String? = null,
)

@Serializable
data class PostPlatformDto(
    val id: String,
    val platform: String,
    @SerialName("content_type") val contentType: String? = null,
    val status: String,
    val meta: JsonObject = JsonObject(emptyMap()),
    @SerialName("platform_url") val platformUrl: String? = null,
    @SerialName("error_message") val errorMessage: String? = null,
    @SerialName("published_at") val publishedAt: String? = null,
    @SerialName("social_account") val socialAccount: SocialAccountDto,
)

@Serializable
data class SocialAccountDto(
    val id: String? = null,
    @SerialName("display_name") val displayName: String,
    val username: String? = null,
    @SerialName("avatar_url") val avatarUrl: String? = null,
)

@Serializable
data class ErrorBodyDto(
    val message: String? = null,
    val errors: Map<String, List<String>> = emptyMap(),
)

@Serializable
data class RescheduleBody(
    @SerialName("scheduled_at") val scheduledAt: String,
)
