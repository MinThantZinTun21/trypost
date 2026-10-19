package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.TaskAttachment
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.network.MobileClient
import com.minthantzintun.trypost.core.network.StartUploadBody
import com.minthantzintun.trypost.core.network.StoreTaskAttachmentBody
import com.minthantzintun.trypost.core.network.map
import javax.inject.Inject
import javax.inject.Singleton

/**
 * A Task's photos, videos and voice notes (spec #130). An upload goes
 * straight to object storage like a Video's, then the server attaches it;
 * the Task is read again afterwards so every screen shows the change.
 */
@Singleton
class TaskAttachmentsRepository @Inject constructor(
    private val client: MobileClient,
    private val uploader: PresignedUploader,
    private val tasksRepository: TasksRepository,
) {
    /** Uploads [source] onto Task [taskId]; [onProgress] runs on the upload thread, from 0 to 1. */
    suspend fun upload(taskId: String, source: UploadSource, onProgress: (Float) -> Unit): ApiResult<TaskAttachment> {
        val started = client.call { startTaskAttachmentUpload(taskId, StartUploadBody(source.fileName, source.size)) }
        if (started !is ApiResult.Success) {
            return started as ApiResult.Failure
        }

        val put = uploader.put(started.value, source, onProgress)
        if (put is ApiResult.Failure) {
            return put
        }

        return client.call { storeTaskAttachment(taskId, StoreTaskAttachmentBody(started.value.uploadId)) }
            .map { it.toTaskAttachment() }
            .also { if (it is ApiResult.Success) announceTask(taskId) }
    }

    /** Removes the attachment and its file. */
    suspend fun delete(taskId: String, attachmentId: String): ApiResult<Unit> =
        client.call { deleteTaskAttachment(taskId, attachmentId) }.also { if (it is ApiResult.Success) announceTask(taskId) }

    private suspend fun announceTask(taskId: String) {
        val task = tasksRepository.task(taskId)
        if (task is ApiResult.Success) {
            tasksRepository.announce(task.value)
        }
    }
}
