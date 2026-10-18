package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Video
import com.minthantzintun.trypost.core.model.VideoPage
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.StartVideoUploadBody
import com.minthantzintun.trypost.core.network.StoreVideoBody
import com.minthantzintun.trypost.core.network.VideoDto
import com.minthantzintun.trypost.core.network.VideoUploadDto
import com.minthantzintun.trypost.core.network.map
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
 * The Owner's Videos (ADR 0013). An upload goes straight to object storage:
 * the server hands out a presigned PUT, the file streams to it with
 * progress, then the server registers it under its name.
 */
@Singleton
class VideosRepository @Inject constructor(
    private val client: MobileClient,
    private val uploadClient: OkHttpClient,
) {
    suspend fun page(page: Int): ApiResult<VideoPage> = client.call { videos(page) }.map { dto ->
        VideoPage(
            videos = dto.data.map { it.toVideo() },
            page = dto.meta.currentPage,
            lastPage = dto.meta.lastPage,
        )
    }

    /** Uploads [source] and registers it as [name]; [onProgress] runs on the upload thread, from 0 to 1. */
    suspend fun upload(source: VideoSource, name: String, onProgress: (Float) -> Unit): ApiResult<Video> {
        val started = client.call { startVideoUpload(StartVideoUploadBody(source.fileName, source.size)) }
        if (started !is ApiResult.Success) {
            return started as ApiResult.Failure
        }

        val put = put(started.value, source, onProgress)
        if (put is ApiResult.Failure) {
            return put
        }

        return client.call { storeVideo(StoreVideoBody(name, started.value.uploadId)) }.map { it.toVideo() }
    }

    suspend fun delete(id: String): ApiResult<Unit> = client.call { deleteVideo(id) }

    /** The PUT to object storage, without the API token: the URL's signature is the only credential. */
    private suspend fun put(upload: VideoUploadDto, source: VideoSource, onProgress: (Float) -> Unit): ApiResult<Unit> {
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

    private fun VideoDto.toVideo(): Video = Video(
        id = id,
        name = name,
        size = size,
        durationSeconds = duration,
        createdAt = createdAt.toInstant(),
        download = downloadLink(downloadUrl, fileName, mimeType = null),
    )
}

/** Streams the file in chunks, reporting each whole percent sent. */
private class ProgressBody(
    private val source: VideoSource,
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
