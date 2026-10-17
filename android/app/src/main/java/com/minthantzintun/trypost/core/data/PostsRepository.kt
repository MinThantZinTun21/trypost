package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostPage
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.map
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PostsRepository @Inject constructor(
    private val client: MobileClient,
) {
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
}
