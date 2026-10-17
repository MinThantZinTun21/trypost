package com.minthantzintun.trypost.feature.detail

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.network.ApiError

/** One validation message, with the field it is about and, for a `platforms.N.…` key, that Social account. */
data class FieldMessage(
    val field: String,
    val accountName: String?,
    val message: String,
)

private val platformKey = Regex("""^platforms\.(\d+)\.""")

/**
 * The server's validation messages for [post]. A `platforms.N.…` key names the
 * Nth Post platform in the order the server lists them.
 */
fun ApiError.Validation.messagesFor(post: Post): List<FieldMessage> = fields.flatMap { (field, messages) ->
    val index = platformKey.find(field)?.groupValues?.get(1)?.toIntOrNull()
    val accountName = index?.let { post.platforms.getOrNull(it)?.accountName }

    messages.map { FieldMessage(field = field, accountName = accountName, message = it) }
}
