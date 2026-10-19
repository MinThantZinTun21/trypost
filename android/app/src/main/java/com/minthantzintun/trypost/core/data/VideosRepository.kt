package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.Video
import com.minthantzintun.trypost.core.model.VideoPage
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.StartUploadBody
import com.minthantzintun.trypost.core.network.StoreVideoBody
import com.minthantzintun.trypost.core.network.VideoDto
import com.minthantzintun.trypost.core.network.map
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
    private val uploader: PresignedUploader,
) {
    suspend fun page(page: Int): ApiResult<VideoPage> = client.call { videos(page) }.map { dto ->
        VideoPage(
            videos = dto.data.map { it.toVideo() },
            page = dto.meta.currentPage,
            lastPage = dto.meta.lastPage,
        )
    }

    /** Uploads [source] and registers it as [name]; [onProgress] runs on the upload thread, from 0 to 1. */
    suspend fun upload(source: UploadSource, name: String, onProgress: (Float) -> Unit): ApiResult<Video> {
        val started = client.call { startVideoUpload(StartUploadBody(source.fileName, source.size)) }
        if (started !is ApiResult.Success) {
            return started as ApiResult.Failure
        }

        val put = uploader.put(started.value, source, onProgress)
        if (put is ApiResult.Failure) {
            return put
        }

        return client.call { storeVideo(StoreVideoBody(name, started.value.uploadId)) }.map { it.toVideo() }
    }

    suspend fun delete(id: String): ApiResult<Unit> = client.call { deleteVideo(id) }

    private fun VideoDto.toVideo(): Video = Video(
        id = id,
        name = name,
        size = size,
        durationSeconds = duration,
        createdAt = createdAt.toInstant(),
        download = downloadLink(downloadUrl, fileName, mimeType = null),
    )
}
