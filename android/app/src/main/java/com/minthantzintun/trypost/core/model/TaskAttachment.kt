package com.minthantzintun.trypost.core.model

import java.time.Instant

/** A Task attachment is a photo, a video or a voice note; documents stay with Posts. */
enum class AttachmentType(val wire: String) {
    Image("image"),
    Video("video"),
    Audio("audio"),
    ;

    companion object {
        fun fromWire(value: String): AttachmentType = entries.firstOrNull { it.wire == value } ?: Image

        /** The type of a picked or recorded file by its MIME type, e.g. `audio/mp4` is a voice note; null when a Task cannot take it. */
        fun fromMimeType(mimeType: String?): AttachmentType? = entries.firstOrNull { mimeType?.lowercase()?.startsWith("${it.wire}/") == true }
    }
}

/** A photo, video or voice note kept on a Task (spec #130). Not a Video, and never part of a Post. */
data class TaskAttachment(
    val id: String,
    val type: AttachmentType,
    /** Where the file is shown or played from. */
    val url: String?,
    val mimeType: String?,
    val fileName: String?,
    val size: Long?,
    val durationSeconds: Double?,
    val createdAt: Instant?,
    val download: DownloadLink?,
)
