package com.minthantzintun.trypost.core.data

import android.content.ContentResolver
import android.net.Uri
import android.provider.OpenableColumns
import java.io.FileNotFoundException
import java.io.InputStream

/** A video from the system Photo Picker. */
class ContentUriVideoSource(
    private val contentResolver: ContentResolver,
    private val uri: Uri,
) : VideoSource {
    override val fileName: String
    override val size: Long
    override val mimeType: String = contentResolver.getType(uri) ?: "video/mp4"

    init {
        var name: String? = null
        var bytes: Long? = null
        contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { cursor ->
            if (cursor.moveToFirst()) {
                name = cursor.getString(0)
                bytes = if (cursor.isNull(1)) null else cursor.getLong(1)
            }
        }
        fileName = name ?: uri.lastPathSegment ?: "video.mp4"
        size = bytes ?: contentResolver.openAssetFileDescriptor(uri, "r")?.use { it.length } ?: 0L
    }

    override fun open(): InputStream =
        contentResolver.openInputStream(uri) ?: throw FileNotFoundException(uri.toString())
}
