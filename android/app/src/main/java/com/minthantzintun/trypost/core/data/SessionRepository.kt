package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileApiFactory
import com.minthantzintun.trypost.core.network.apiCall
import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.session.SessionStore
import kotlinx.coroutines.flow.Flow
import kotlinx.serialization.json.Json
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import javax.inject.Inject
import javax.inject.Singleton

/** Signs the Owner in with their server address and `MOBILE_TOKEN`, and out again. */
@Singleton
class SessionRepository @Inject constructor(
    private val sessionStore: SessionStore,
    private val apiFactory: MobileApiFactory,
    private val json: Json,
) {
    val session: Flow<Session?> = sessionStore.session

    /** Checks the token against `/mobile/v1/me` and keeps it only when the server accepts it. */
    suspend fun signIn(serverUrl: String, token: String): SignInResult {
        val normalizedUrl = normalize(serverUrl) ?: return SignInResult.InvalidAddress
        val trimmedToken = token.trim()

        if (trimmedToken.isEmpty()) {
            return SignInResult.MissingToken
        }

        val api = apiFactory.create(normalizedUrl, trimmedToken)

        return when (val result = apiCall(json) { api.me() }) {
            is ApiResult.Success -> {
                sessionStore.save(Session(serverUrl = normalizedUrl, token = trimmedToken, ownerName = result.value.name))
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

    data object MissingToken : SignInResult

    data class Failed(val error: ApiError) : SignInResult
}
