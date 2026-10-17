package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostPage
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.RescheduleBody
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

    /** Schedules a Draft or moves a Scheduled post; the server runs the composer's checks. */
    suspend fun reschedule(id: String, at: Instant): ApiResult<Post> =
        client.call { reschedule(id, RescheduleBody(at.toString())) }.map { it.toPost() }.announced()

    /** Turns a Scheduled post back into a Draft. */
    suspend fun unschedule(id: String): ApiResult<Post> =
        client.call { unschedule(id) }.map { it.toPost() }.announced()

    private fun ApiResult<Post>.announced(): ApiResult<Post> = also {
        if (it is ApiResult.Success) {
            postChanges.announce(it.value)
        }
    }
}
