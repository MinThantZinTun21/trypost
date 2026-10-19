package com.minthantzintun.trypost.core.network

import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import retrofit2.HttpException
import java.io.IOException
import kotlin.coroutines.cancellation.CancellationException

sealed interface ApiResult<out T> {
    data class Success<T>(val value: T) : ApiResult<T>

    data class Failure(val error: ApiError) : ApiResult<Nothing>
}

/** Why a `/mobile/v1` call did not succeed, in the terms the screens show. */
sealed interface ApiError {
    /** 401: the Mobile token was revoked by Sign out or a new password. */
    data object Unauthorized : ApiError

    /** 404: the address is wrong, or the Post or Video is gone. */
    data object NotFound : ApiError

    /** The server could not be reached at all. */
    data object Unreachable : ApiError

    /** 409: the Post is publishing, Published or Failed and cannot change. */
    data class Conflict(val message: String?) : ApiError

    /** 422: the server's validation errors, keyed by field. */
    data class Validation(val message: String?, val fields: Map<String, List<String>>) : ApiError

    /** Anything else the server answered with, or a reply the app cannot read. */
    data class Server(val code: Int?) : ApiError
}

/** Runs one API call and turns its failure into an [ApiError]. */
suspend fun <T> apiCall(json: Json, block: suspend () -> T): ApiResult<T> = try {
    ApiResult.Success(block())
} catch (exception: CancellationException) {
    throw exception
} catch (exception: HttpException) {
    ApiResult.Failure(exception.toApiError(json))
} catch (exception: IOException) {
    ApiResult.Failure(ApiError.Unreachable)
} catch (exception: SerializationException) {
    ApiResult.Failure(ApiError.Server(null))
}

private fun HttpException.toApiError(json: Json): ApiError {
    val body = response()?.errorBody()?.string()?.let { raw ->
        runCatching { json.decodeFromString(ErrorBodyDto.serializer(), raw) }.getOrNull()
    }

    return when (code()) {
        401 -> ApiError.Unauthorized
        404 -> ApiError.NotFound
        409 -> ApiError.Conflict(body?.message)
        422 -> ApiError.Validation(body?.message, body?.errors.orEmpty())
        else -> ApiError.Server(code())
    }
}

inline fun <T, R> ApiResult<T>.map(transform: (T) -> R): ApiResult<R> = when (this) {
    is ApiResult.Success -> ApiResult.Success(transform(value))
    is ApiResult.Failure -> this
}
