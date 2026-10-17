package com.minthantzintun.trypost.core.network

import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.session.SessionStore
import kotlinx.serialization.json.Json
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Calls the API as the signed-in Owner. A 401 means the token was rotated or
 * revoked, so it signs the app out rather than leave it half working.
 */
@Singleton
class MobileClient @Inject constructor(
    private val sessionStore: SessionStore,
    private val apiFactory: MobileApiFactory,
    private val json: Json,
) {
    suspend fun <T> call(block: suspend MobileApi.() -> T): ApiResult<T> {
        val session = sessionStore.current() ?: return ApiResult.Failure(ApiError.Unauthorized)
        val api = apiFor(session)
        val result = apiCall(json) { api.block() }

        if (result is ApiResult.Failure && result.error == ApiError.Unauthorized) {
            sessionStore.clear()
        }

        return result
    }

    @Volatile
    private var cached: Pair<Session, MobileApi>? = null

    private fun apiFor(session: Session): MobileApi {
        cached?.let { (cachedSession, api) -> if (cachedSession == session) return api }

        return apiFactory.create(session.serverUrl, session.token).also { cached = session to it }
    }
}
