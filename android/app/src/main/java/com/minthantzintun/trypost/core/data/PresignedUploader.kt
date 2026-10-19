package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.PresignedUploadDto
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okio.BufferedSink
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton

/**
 * The PUT of a file to object storage at a presigned URL the server handed
 * out, for Videos and Task attachments alike. It carries no API token: the
 * URL's signature is the only credential.
 */
@Singleton
class PresignedUploader @Inject constructor(
    private val uploadClient: OkHttpClient,
) {
    /** Streams [source] to [upload]; [onProgress] runs on the upload thread, from 0 to 1. */
    suspend fun put(upload: PresignedUploadDto, source: UploadSource, onProgress: (Float) -> Unit): ApiResult<Unit> {
        val contentType = upload.headers.entries.firstOrNull { it.key.equals("Content-Type", ignoreCase = true) }?.value ?: source.mimeType
        val request = Request.Builder()
            .url(upload.url)
            .apply {
                upload.headers
                    .filterKeys { !it.equals("Content-Type", ignoreCase = true) }
                    .forEach { (header, value) -> header(header, value) }
            }
            .put(ProgressBody(source, contentType.toMediaTypeOrNull(), onProgress))
            .build()

        return withContext(Dispatchers.IO) {
            try {
                uploadClient.newCall(request).execute().use { response ->
                    if (response.isSuccessful) ApiResult.Success(Unit) else ApiResult.Failure(ApiError.Server(response.code))
                }
            } catch (exception: IOException) {
                ApiResult.Failure(ApiError.Unreachable)
            }
        }
    }
}

/** Streams the file in chunks, reporting each whole percent sent. */
private class ProgressBody(
    private val source: UploadSource,
    private val contentType: MediaType?,
    private val onProgress: (Float) -> Unit,
) : RequestBody() {
    override fun contentType(): MediaType? = contentType

    override fun contentLength(): Long = source.size

    override fun isOneShot(): Boolean = true

    override fun writeTo(sink: BufferedSink) {
        val total = source.size.coerceAtLeast(1)
        val buffer = ByteArray(CHUNK_BYTES)
        var sent = 0L
        var lastPercent = -1L

        source.open().use { input ->
            while (true) {
                val read = input.read(buffer)
                if (read == -1) {
                    break
                }
                sink.write(buffer, 0, read)
                sent += read

                val percent = sent * 100 / total
                if (percent != lastPercent) {
                    lastPercent = percent
                    onProgress((sent.toFloat() / total).coerceAtMost(1f))
                }
            }
        }
    }

    private companion object {
        const val CHUNK_BYTES = 64 * 1024
    }
}
