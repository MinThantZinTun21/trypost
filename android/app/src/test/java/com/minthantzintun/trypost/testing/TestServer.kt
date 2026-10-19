package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.di.AppModule
import com.minthantzintun.trypost.core.network.MobileApiFactory
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.session.Session
import kotlinx.serialization.json.JsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.OkHttpClient

val testJson = AppModule.provideJson()

fun testApiFactory(): MobileApiFactory = MobileApiFactory(OkHttpClient(), testJson)

fun MockWebServer.serverUrl(): String = url("/").toString().trimEnd('/')

fun MockWebServer.session(token: String = "good-token"): Session =
    Session(serverUrl = serverUrl(), token = token, ownerName = "The Owner")

fun testClient(store: FakeSessionStore): MobileClient = MobileClient(store, testApiFactory(), testJson)

fun json(body: String, code: Int = 200): MockResponse = MockResponse.Builder()
    .code(code)
    .addHeader("Content-Type", "application/json")
    .body(body)
    .build()

/** One `/mobile/v1/posts` page with the given post JSON objects. */
fun postsPage(posts: List<String>, page: Int = 1, lastPage: Int = 1): MockResponse =
    json("""{"data":[${posts.joinToString(",")}],"meta":{"current_page":$page,"last_page":$lastPage}}""")

fun postJson(
    id: String,
    status: String = "scheduled",
    content: String = "Post $id",
    scheduledAt: String? = "2030-01-02T09:30:00+00:00",
    publishedAt: String? = null,
): String {
    val scheduled = scheduledAt?.let { "\"$it\"" } ?: "null"
    val published = publishedAt?.let { "\"$it\"" } ?: "null"

    return """
        {"id":"$id","status":"$status","content":"$content","scheduled_at":$scheduled,"published_at":$published,
         "created_at":"2029-12-30T10:00:00+00:00","editable":true,
         "media":[{"id":"m1","type":"video","url":"https://cdn.example.com/m1.mp4","mime_type":"video/mp4","original_filename":"clip.mp4","download_url":"https://app.example.com/media/m1/download?signature=abc","file_name":"clip.mp4"}],
         "platforms":[{"id":"pp1","platform":"facebook","content_type":"facebook_post","status":"pending","meta":{},
           "platform_url":null,"error_message":null,"published_at":null,
           "social_account":{"id":"sa1","display_name":"My Page","username":"mypage","avatar_url":null}}]}
    """.trimIndent()
}

/** One `/mobile/v1/videos` page with the given video JSON objects. */
fun videosPage(videos: List<String>, page: Int = 1, lastPage: Int = 1): MockResponse =
    json("""{"data":[${videos.joinToString(",")}],"meta":{"current_page":$page,"last_page":$lastPage}}""")

fun videoJson(
    id: String,
    name: String = "Video $id",
    size: Long? = 5_242_880,
    duration: Double? = 75.5,
    downloadUrl: String? = "https://app.example.com/media/f-$id/download?signature=abc",
): String {
    val download = downloadUrl?.let { "\"$it\"" } ?: "null"
    val fileName = downloadUrl?.let { "\"$name.mp4\"" } ?: "null"

    return """{"id":"$id","name":"$name","size":${size ?: "null"},"duration":${duration ?: "null"},"created_at":"2030-01-02T09:30:00+00:00","download_url":$download,"file_name":$fileName}"""
}

/** The reply to `POST /mobile/v1/videos/uploads`, pointing the PUT back at this server. */
fun MockWebServer.videoUploadStarted(uploadId: String = "up-1"): MockResponse =
    json("""{"upload_id":"$uploadId","url":"${url("/bucket/raw.mp4?X-Amz-Signature=abc")}","headers":{"Content-Type":"video/mp4"},"expires_in_seconds":3600}""")

/** One `/mobile/v1/tasks` page with the given task JSON objects. */
fun tasksPage(tasks: List<String>, page: Int = 1, lastPage: Int = 1): MockResponse =
    json("""{"data":[${tasks.joinToString(",")}],"meta":{"current_page":$page,"last_page":$lastPage}}""")

fun taskJson(
    id: String,
    title: String? = "Task $id",
    status: String = "todo",
    priority: Int = 0,
    dueAt: String? = null,
    dueAllDay: Boolean = false,
    description: String? = null,
    taskTags: List<String> = emptyList(),
    color: String? = null,
    pinned: Boolean = false,
    /** More fields as JSON, e.g. `"reminders":[...]`. */
    extra: String? = null,
): String {
    val due = dueAt?.let { "\"$it\"" } ?: "null"
    val completed = if (status == "done") "\"2030-01-03T10:00:00+00:00\"" else "null"

    return """
        {"id":"$id","title":${JsonPrimitive(title)},"description":${JsonPrimitive(description)},"status":"$status","priority":$priority,
         "color":${JsonPrimitive(color)},"pinned":$pinned,
         "due_at":$due,"due_all_day":$dueAllDay,"completed_at":$completed,
         "created_at":"2030-01-01T08:00:00+00:00","updated_at":"2030-01-01T08:00:00+00:00",
         "task_tags":[${taskTags.joinToString(",")}]${extra?.let { ",$it" }.orEmpty()}}
    """.trimIndent()
}

fun reminderJson(
    id: String,
    taskId: String = "t1",
    remindAt: String = "2030-01-01T05:00:00+00:00",
    minutesBefore: Int? = null,
    repeats: Boolean = false,
    dismissedAt: String? = null,
    taskTitle: String? = null,
): String {
    val type = if (minutesBefore == null) "at" else "before_due"
    val task = taskTitle?.let { ""","task":{"id":"$taskId","title":"$it","description":null}""" }.orEmpty()

    return """{"id":"$id","task_id":"$taskId","type":"$type","remind_at":"$remindAt","minutes_before":${minutesBefore ?: "null"},""" +
        """"repeat_until_dismissed":$repeats,"dismissed_at":${dismissedAt?.let { "\"$it\"" } ?: "null"}$task}"""
}

fun taskAttachmentJson(id: String, type: String = "image", mimeType: String = "image/jpeg", fileName: String = "photo.jpg"): String =
    """{"id":"$id","type":"$type","url":"https://cdn.example.com/$fileName","mime_type":"$mimeType","original_filename":"$fileName",""" +
        """"size":1024,"duration":null,"created_at":"2030-01-01T08:00:00+00:00","download_url":"https://app.example.com/media/$id/download?signature=abc","file_name":"$fileName"}"""

/** A Task tag as a Task carries it. */
fun taskTagRefJson(id: String, name: String = "Tag $id", groupId: String = "g1", groupName: String = "Context", color: String = "blue"): String =
    """{"id":"$id","name":"$name","tag_group_id":"$groupId","tag_group_name":"$groupName","color":"$color"}"""

/** A Tag group with Task tags named by their ids. */
fun tagGroupJson(id: String, name: String = "Group $id", color: String = "blue", taskTagIds: List<String> = emptyList()): String {
    val taskTags = taskTagIds.joinToString(",") { """{"id":"$it","name":"Tag $it","tag_group_id":"$id"}""" }

    return """{"id":"$id","name":"$name","color":"$color","task_tags":[$taskTags]}"""
}

fun tagGroupsList(groups: List<String>): MockResponse = json("""{"data":[${groups.joinToString(",")}]}""")

/** Laravel's 422 with the given field messages. */
fun validationError(vararg fields: Pair<String, String>): MockResponse {
    val errors = fields.joinToString(",") { (key, message) -> "\"$key\":[\"$message\"]" }

    return json("""{"message":"The given data was invalid.","errors":{$errors}}""", code = 422)
}

fun noContent(): MockResponse = MockResponse.Builder().code(204).build()
