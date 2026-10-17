package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostPage
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.model.PlatformText
import com.minthantzintun.trypost.core.network.PlatformTextsBody
import com.minthantzintun.trypost.core.network.RescheduleBody
import com.minthantzintun.trypost.core.network.UpdatePostTextsBody
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import com.minthantzintun.trypost.core.network.map
import kotlinx.coroutines.flow.SharedFlow
import java.time.Instant
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PostsRepository @Inject constructor(
    private val client: MobileClient,
    private val postChanges: PostChanges,
) {
    /** Posts changed through this repository. */
    val changes: SharedFlow<Post> get() = postChanges.posts

    suspend fun page(tab: PostTab, page: Int): ApiResult<PostPage> = client.call {
        posts(tab = tab.wire, page = page)
    }.map { dto ->
        PostPage(
            posts = dto.data.map { it.toPost() },
            page = dto.meta.currentPage,
            lastPage = dto.meta.lastPage,
        )
    }

    suspend fun post(id: String): ApiResult<Post> = client.call { post(id) }.map { it.toPost() }

    /** Moves a Scheduled post; the server runs the composer's checks. */
    suspend fun reschedule(id: String, at: Instant): ApiResult<Post> =
        client.call { reschedule(id, RescheduleBody(at.toString())) }.map { it.toPost() }.announced()

    /**
     * Saves the Post content and each Post platform's texts, keyed by Post
     * platform id. A blank text is cleared so the Post content stands in;
     * tags are a comma separated line. Platforms go in [platformOrder], the
     * order the server lists them, so a `platforms.N` error names the Nth.
     */
    suspend fun updateTexts(
        id: String,
        content: String,
        platformOrder: List<String>,
        texts: Map<String, Map<PlatformText, String>>,
    ): ApiResult<Post> {
        val body = UpdatePostTextsBody(
            content = content,
            platforms = platformOrder.map { platformId ->
                PlatformTextsBody(
                    id = platformId,
                    meta = JsonObject(texts[platformId].orEmpty().map { (text, value) -> text.metaKey to text.toJson(value) }.toMap()),
                )
            },
        )

        return client.call { update(id, body) }.map { it.toPost() }.announced()
    }

    private fun PlatformText.toJson(value: String): JsonElement = when {
        this == PlatformText.Tags -> JsonArray(value.split(',').map { it.trim() }.filter { it.isNotEmpty() }.map(::JsonPrimitive))
        value.isBlank() -> JsonNull
        else -> JsonPrimitive(value)
    }

    private fun ApiResult<Post>.announced(): ApiResult<Post> = also {
        if (it is ApiResult.Success) {
            postChanges.announce(it.value)
        }
    }
}
