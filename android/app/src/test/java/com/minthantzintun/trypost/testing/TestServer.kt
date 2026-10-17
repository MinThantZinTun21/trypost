package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.di.AppModule
import com.minthantzintun.trypost.core.network.MobileApiFactory
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.session.Session
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
         "media":[{"id":"m1","type":"video","url":"https://cdn.example.com/m1.mp4","mime_type":"video/mp4","original_filename":"clip.mp4"}],
         "platforms":[{"id":"pp1","platform":"facebook","content_type":"facebook_post","status":"pending","meta":{},
           "platform_url":null,"error_message":null,"published_at":null,
           "social_account":{"id":"sa1","display_name":"My Page","username":"mypage","avatar_url":null}}]}
    """.trimIndent()
}
