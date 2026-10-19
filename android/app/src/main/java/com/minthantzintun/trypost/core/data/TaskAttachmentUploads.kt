package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.di.ApplicationScope
import com.minthantzintun.trypost.core.model.AttachmentType
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.util.concurrent.atomic.AtomicLong
import javax.inject.Inject
import javax.inject.Singleton

/** A Task attachment on its way up, or one that failed and can be tried again. */
data class AttachmentUpload(
    val id: Long,
    val taskId: String,
    val fileName: String,
    val type: AttachmentType,
    /** From 0 to 1. */
    val progress: Float = 0f,
    val error: ApiError? = null,
)

/**
 * Task attachment uploads, which go on while the app is open even when the
 * Owner leaves the Task (no WorkManager). A finished one disappears, since
 * the Task then lists it.
 */
@Singleton
class TaskAttachmentUploads @Inject constructor(
    private val repository: TaskAttachmentsRepository,
    @ApplicationScope private val scope: CoroutineScope,
) {
    private val nextId = AtomicLong()
    private val sources = mutableMapOf<Long, UploadSource>()
    private val _uploads = MutableStateFlow<List<AttachmentUpload>>(emptyList())

    val uploads: StateFlow<List<AttachmentUpload>> = _uploads.asStateFlow()

    fun start(taskId: String, source: UploadSource, type: AttachmentType) {
        val upload = AttachmentUpload(id = nextId.incrementAndGet(), taskId = taskId, fileName = source.fileName, type = type)
        synchronized(sources) { sources[upload.id] = source }
        _uploads.update { it + upload }
        run(upload)
    }

    /** Tries a failed upload again. */
    fun retry(id: Long) {
        val upload = _uploads.value.firstOrNull { it.id == id && it.error != null } ?: return
        change(id) { it.copy(progress = 0f, error = null) }
        run(upload)
    }

    /** Forgets a failed upload. */
    fun dismiss(id: Long) {
        synchronized(sources) { sources.remove(id) }
        _uploads.update { uploads -> uploads.filterNot { it.id == id } }
    }

    private fun run(upload: AttachmentUpload) {
        val source = synchronized(sources) { sources[upload.id] } ?: return

        scope.launch {
            val result = repository.upload(upload.taskId, source) { progress -> change(upload.id) { it.copy(progress = progress) } }
            when (result) {
                is ApiResult.Success -> dismiss(upload.id)
                is ApiResult.Failure -> change(upload.id) { it.copy(error = result.error) }
            }
        }
    }

    private fun change(id: Long, transform: (AttachmentUpload) -> AttachmentUpload) {
        _uploads.update { uploads -> uploads.map { if (it.id == id) transform(it) else it } }
    }
}
