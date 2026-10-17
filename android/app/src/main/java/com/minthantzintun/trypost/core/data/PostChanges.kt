package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Post
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import javax.inject.Inject
import javax.inject.Singleton

/** Posts this app just changed, so a screen still on the back stack can reload. */
@Singleton
class PostChanges @Inject constructor() {
    private val changes = MutableSharedFlow<Post>(extraBufferCapacity = 8)

    val posts: SharedFlow<Post> = changes.asSharedFlow()

    fun announce(post: Post) {
        changes.tryEmit(post)
    }
}
