package com.minthantzintun.trypost.feature.detail

import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.platformForErrorKey
import com.minthantzintun.trypost.core.network.ApiError

/** One validation message, with the field it is about and, for a `platforms.N.…` key, that Social account. */
data class FieldMessage(
    val field: String,
    val accountName: String?,
    val message: String,
)

/**
 * The server's validation messages for [post], each with the Social account a
 * `platforms.N.…` key names.
 */
fun ApiError.Validation.messagesFor(post: Post): List<FieldMessage> = fields.flatMap { (field, messages) ->
    val accountName = post.platformForErrorKey(field)?.accountName

    messages.map { FieldMessage(field = field, accountName = accountName, message = it) }
}
