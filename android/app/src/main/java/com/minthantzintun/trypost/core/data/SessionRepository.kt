package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileApiFactory
import com.minthantzintun.trypost.core.network.SignInBody
import com.minthantzintun.trypost.core.network.apiCall
import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.session.SessionStore
import kotlinx.coroutines.flow.Flow
import kotlinx.serialization.json.Json
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import javax.inject.Inject
import javax.inject.Singleton

/** Signs the Owner in with their server address and web email and password, and out again. */
@Singleton
class SessionRepository @Inject constructor(
    private val sessionStore: SessionStore,
    private val apiFactory: MobileApiFactory,
    private val json: Json,
) {
    val session: Flow<Session?> = sessionStore.session

    /**
     * Sends the web email and password to `/mobile/v1/sessions` and keeps the
     * Mobile token the server hands back. The password is sent as typed.
     */
    suspend fun signIn(serverUrl: String, email: String, password: String): SignInResult {
        val normalizedUrl = normalize(serverUrl) ?: return SignInResult.InvalidAddress
        val trimmedEmail = email.trim()

        if (trimmedEmail.isEmpty()) {
            return SignInResult.MissingEmail
        }

        if (password.isEmpty()) {
            return SignInResult.MissingPassword
        }

        val api = apiFactory.create(normalizedUrl)

        return when (val result = apiCall(json) { api.signIn(SignInBody(email = trimmedEmail, password = password)) }) {
            is ApiResult.Success -> {
                sessionStore.save(Session(serverUrl = normalizedUrl, token = result.value.token, ownerName = result.value.name))
                SignInResult.SignedIn
            }
            is ApiResult.Failure -> SignInResult.Failed(result.error)
        }
    }

    suspend fun signOut() {
        sessionStore.clear()
    }

    /** An http(s) address without a trailing slash, or `null` when it is not one. */
    private fun normalize(serverUrl: String): String? {
        val trimmed = serverUrl.trim().trimEnd('/')
        val withScheme = if (trimmed.contains("://")) trimmed else "https://$trimmed"
        val url = withScheme.toHttpUrlOrNull() ?: return null

        return url.toString().trimEnd('/')
    }
}

sealed interface SignInResult {
    data object SignedIn : SignInResult

    data object InvalidAddress : SignInResult

    data object MissingEmail : SignInResult

    data object MissingPassword : SignInResult

    data class Failed(val error: ApiError) : SignInResult
}
