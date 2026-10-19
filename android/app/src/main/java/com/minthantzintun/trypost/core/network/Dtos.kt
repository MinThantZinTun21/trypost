package com.minthantzintun.trypost.core.network

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

/*
 * The `/mobile/v1` wire format (`App\Http\Resources\Mobile\V1`). Times are
 * ISO 8601 strings in UTC.
 */

@Serializable
data class SignInBody(
    val email: String,
    val password: String,
)

/** A new Mobile token for this phone, shown once, and the Owner's name. */
@Serializable
data class SignInDto(
    val token: String,
    val name: String,
)

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
    @SerialName("download_url") val downloadUrl: String? = null,
    @SerialName("file_name") val fileName: String? = null,
)

@Serializable
data class PostPlatformDto(
    val id: String,
    val platform: String,
    @SerialName("content_type") val contentType: String? = null,
    val status: String,
    val meta: JsonObject = JsonObject(emptyMap()),
    @SerialName("text_fields") val textFields: List<String> = emptyList(),
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

/** An unpaginated `{"data": [...]}` list, such as the Social accounts. */
@Serializable
data class ListDto<T>(val data: List<T>)

/** A Social account the post list can be filtered to (`SocialAccountResource`). */
@Serializable
data class AccountDto(
    val id: String,
    val platform: String,
    @SerialName("display_label") val displayLabel: String,
    val status: String,
)

/** A Video (`App\Http\Resources\Mobile\V1\VideoResource`). */
@Serializable
data class VideoDto(
    val id: String,
    val name: String,
    val size: Long? = null,
    val duration: Double? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("download_url") val downloadUrl: String? = null,
    @SerialName("file_name") val fileName: String? = null,
)

@Serializable
data class StartVideoUploadBody(
    @SerialName("file_name") val fileName: String,
    val size: Long,
)

/** Where to PUT the file: a presigned object storage URL with the headers it was signed with. */
@Serializable
data class VideoUploadDto(
    @SerialName("upload_id") val uploadId: String,
    val url: String,
    val headers: Map<String, String> = emptyMap(),
)

@Serializable
data class StoreVideoBody(
    val name: String,
    @SerialName("upload_id") val uploadId: String,
)

/** A reply that only says what the server did, such as a queued Send to Telegram. */
@Serializable
data class MessageDto(val message: String)

@Serializable
data class ErrorBodyDto(
    val message: String? = null,
    val errors: Map<String, List<String>> = emptyMap(),
)

@Serializable
data class RescheduleBody(
    @SerialName("scheduled_at") val scheduledAt: String,
)

@Serializable
data class UpdatePostTextsBody(
    val content: String,
    val platforms: List<PlatformTextsBody>,
)

/** One Post platform's texts; a JSON null clears a text so the Post content stands in. */
@Serializable
data class PlatformTextsBody(
    val id: String,
    val meta: JsonObject,
)

/** A Task (`App\Http\Resources\Mobile\V1\TaskResource`). */
@Serializable
data class TaskDto(
    val id: String,
    val title: String,
    val description: String? = null,
    val status: String = "todo",
    val priority: Int = 0,
    @SerialName("due_at") val dueAt: String? = null,
    @SerialName("due_all_day") val dueAllDay: Boolean = false,
    @SerialName("completed_at") val completedAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("task_tags") val taskTags: List<TaskTagRefDto> = emptyList(),
)

/** A Task tag inside a Task, with its Tag group's name and colour. */
@Serializable
data class TaskTagRefDto(
    val id: String,
    val name: String,
    @SerialName("tag_group_id") val tagGroupId: String,
    @SerialName("tag_group_name") val tagGroupName: String = "",
    val color: String? = null,
)

@Serializable
data class TagGroupDto(
    val id: String,
    val name: String,
    val color: String? = null,
    @SerialName("task_tags") val taskTags: List<TaskTagDto> = emptyList(),
)

@Serializable
data class TaskTagDto(
    val id: String,
    val name: String,
    @SerialName("tag_group_id") val tagGroupId: String,
)

/**
 * A Task's create or update body. A save replaces the whole Task, so every
 * field is sent; a null is left out, which the server reads as cleared.
 */
@Serializable
data class TaskBody(
    val title: String,
    val description: String?,
    val priority: Int,
    @SerialName("due_at") val dueAt: String?,
    @SerialName("due_all_day") val dueAllDay: Boolean,
    @SerialName("task_tags") val taskTags: List<String>,
)

@Serializable
data class TaskStatusBody(val status: String)

@Serializable
data class TagGroupBody(
    val name: String,
    val color: String,
)

@Serializable
data class TaskTagBody(val name: String)
