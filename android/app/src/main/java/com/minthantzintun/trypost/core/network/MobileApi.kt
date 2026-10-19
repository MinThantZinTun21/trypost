package com.minthantzintun.trypost.core.network

import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query

/** The server's `/mobile/v1` API (ADR 0010). */
interface MobileApi {
    @POST("mobile/v1/sessions")
    suspend fun signIn(@Body body: SignInBody): SignInDto

    @DELETE("mobile/v1/session")
    suspend fun signOut()

    @GET("mobile/v1/posts")
    suspend fun posts(
        @Query("status") tab: String,
        @Query("page") page: Int,
        @Query("account") account: String? = null,
    ): PageDto<PostDto>

    @GET("mobile/v1/social-accounts")
    suspend fun socialAccounts(): ListDto<AccountDto>

    @GET("mobile/v1/posts/{post}")
    suspend fun post(@Path("post") id: String): PostDto

    @PUT("mobile/v1/posts/{post}")
    suspend fun update(@Path("post") id: String, @Body body: UpdatePostTextsBody): PostDto

    @PUT("mobile/v1/posts/{post}/schedule")
    suspend fun reschedule(@Path("post") id: String, @Body body: RescheduleBody): PostDto

    @POST("mobile/v1/posts/{post}/publish")
    suspend fun publishNow(@Path("post") id: String): PostDto

    @POST("mobile/v1/posts/{post}/telegram")
    suspend fun sendToTelegram(@Path("post") id: String): MessageDto

    @GET("mobile/v1/videos")
    suspend fun videos(@Query("page") page: Int): PageDto<VideoDto>

    @POST("mobile/v1/videos/uploads")
    suspend fun startVideoUpload(@Body body: StartUploadBody): PresignedUploadDto

    @POST("mobile/v1/videos")
    suspend fun storeVideo(@Body body: StoreVideoBody): VideoDto

    @DELETE("mobile/v1/videos/{video}")
    suspend fun deleteVideo(@Path("video") id: String)

    @GET("mobile/v1/tasks")
    suspend fun tasks(
        @Query("tab") tab: String,
        @Query("page") page: Int,
        @Query("task_tags[]") taskTags: List<String> = emptyList(),
        @Query("q") query: String? = null,
    ): PageDto<TaskDto>

    @POST("mobile/v1/tasks")
    suspend fun createTask(@Body body: SaveTaskBody): TaskDto

    @GET("mobile/v1/tasks/{task}")
    suspend fun task(@Path("task") id: String): TaskDto

    @PUT("mobile/v1/tasks/{task}")
    suspend fun updateTask(@Path("task") id: String, @Body body: SaveTaskBody): TaskDto

    @PUT("mobile/v1/tasks/{task}/status")
    suspend fun updateTaskStatus(@Path("task") id: String, @Body body: TaskStatusBody): TaskDto

    @DELETE("mobile/v1/tasks/{task}")
    suspend fun deleteTask(@Path("task") id: String)

    @GET("mobile/v1/reminders/upcoming")
    suspend fun upcomingReminders(): ListDto<ReminderDto>

    @POST("mobile/v1/reminders/{reminder}/dismiss")
    suspend fun dismissReminder(@Path("reminder") id: String): ReminderDto

    @POST("mobile/v1/tasks/{task}/attachments/uploads")
    suspend fun startTaskAttachmentUpload(@Path("task") taskId: String, @Body body: StartUploadBody): PresignedUploadDto

    @POST("mobile/v1/tasks/{task}/attachments")
    suspend fun storeTaskAttachment(@Path("task") taskId: String, @Body body: StoreTaskAttachmentBody): TaskAttachmentDto

    @DELETE("mobile/v1/tasks/{task}/attachments/{media}")
    suspend fun deleteTaskAttachment(@Path("task") taskId: String, @Path("media") id: String)

    @GET("mobile/v1/tag-groups")
    suspend fun tagGroups(): ListDto<TagGroupDto>

    @POST("mobile/v1/tag-groups")
    suspend fun createTagGroup(@Body body: TagGroupBody): TagGroupDto

    @PUT("mobile/v1/tag-groups/{tagGroup}")
    suspend fun updateTagGroup(@Path("tagGroup") id: String, @Body body: TagGroupBody): TagGroupDto

    @DELETE("mobile/v1/tag-groups/{tagGroup}")
    suspend fun deleteTagGroup(@Path("tagGroup") id: String)

    @POST("mobile/v1/tag-groups/{tagGroup}/task-tags")
    suspend fun createTaskTag(@Path("tagGroup") tagGroupId: String, @Body body: TaskTagBody): TaskTagDto

    @PUT("mobile/v1/task-tags/{taskTag}")
    suspend fun updateTaskTag(@Path("taskTag") id: String, @Body body: TaskTagBody): TaskTagDto

    @DELETE("mobile/v1/task-tags/{taskTag}")
    suspend fun deleteTaskTag(@Path("taskTag") id: String)
}
