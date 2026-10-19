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

/** The file a presigned upload is for: Videos and Task attachments alike. */
@Serializable
data class StartUploadBody(
    @SerialName("file_name") val fileName: String,
    val size: Long,
)

/** Where to PUT the file: a presigned object storage URL with the headers it was signed with. */
@Serializable
data class PresignedUploadDto(
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
    val title: String? = null,
    val description: String? = null,
    val status: String = "todo",
    val priority: Int = 0,
    @SerialName("due_at") val dueAt: String? = null,
    @SerialName("due_all_day") val dueAllDay: Boolean = false,
    @SerialName("completed_at") val completedAt: String? = null,
    val color: String? = null,
    val pinned: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("task_tags") val taskTags: List<TaskTagRefDto> = emptyList(),
    val reminders: List<ReminderDto> = emptyList(),
    @SerialName("tracked_seconds") val trackedSeconds: Long = 0,
    @SerialName("timer_sessions") val timerSessions: List<TimerSessionDto> = emptyList(),
    val attachments: List<TaskAttachmentDto> = emptyList(),
)

/** A Reminder (`ReminderResource`); the upcoming list also names its Task. */
@Serializable
data class ReminderDto(
    val id: String,
    @SerialName("task_id") val taskId: String,
    val type: String,
    @SerialName("remind_at") val remindAt: String,
    @SerialName("minutes_before") val minutesBefore: Int? = null,
    @SerialName("repeat_until_dismissed") val repeatUntilDismissed: Boolean = false,
    @SerialName("dismissed_at") val dismissedAt: String? = null,
    val task: ReminderTaskDto? = null,
)

/** What a reminder's notification says about its Task. */
@Serializable
data class ReminderTaskDto(
    val id: String,
    val title: String? = null,
    val description: String? = null,
)

/** A Timer session (`TimerSessionResource`); the running one also names its Task. */
@Serializable
data class TimerSessionDto(
    val id: String,
    @SerialName("task_id") val taskId: String,
    val mode: String,
    @SerialName("started_at") val startedAt: String,
    @SerialName("ended_at") val endedAt: String? = null,
    @SerialName("planned_seconds") val plannedSeconds: Long? = null,
    val outcome: String? = null,
    val task: ReminderTaskDto? = null,
)

/** `GET timer`: the running Timer session, or null. */
@Serializable
data class RunningTimerDto(val data: TimerSessionDto? = null)

@Serializable
data class StartTimerBody(
    val mode: String,
    @SerialName("planned_seconds") val plannedSeconds: Long?,
)

/** A Task attachment (`TaskAttachmentResource`). */
@Serializable
data class TaskAttachmentDto(
    val id: String,
    val type: String,
    val url: String? = null,
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("original_filename") val originalFilename: String? = null,
    val size: Long? = null,
    val duration: Double? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("download_url") val downloadUrl: String? = null,
    @SerialName("file_name") val fileName: String? = null,
)

@Serializable
data class StoreTaskAttachmentBody(
    @SerialName("upload_id") val uploadId: String,
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
data class SaveTaskBody(
    val title: String?,
    val description: String?,
    val priority: Int,
    val color: String?,
    val pinned: Boolean,
    @SerialName("due_at") val dueAt: String?,
    @SerialName("due_all_day") val dueAllDay: Boolean,
    @SerialName("task_tags") val taskTags: List<String>,
    /** The whole set of reminders; null leaves the stored ones as they are. */
    val reminders: List<ReminderBody>? = null,
)

/** One reminder in a save: `at` a time with its UTC offset, or `before_due` by some minutes. */
@Serializable
data class ReminderBody(
    val type: String,
    @SerialName("remind_at") val remindAt: String? = null,
    @SerialName("minutes_before") val minutesBefore: Int? = null,
    @SerialName("repeat_until_dismissed") val repeatUntilDismissed: Boolean,
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
