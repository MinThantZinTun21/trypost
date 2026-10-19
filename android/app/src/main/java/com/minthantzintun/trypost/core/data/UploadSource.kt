package com.minthantzintun.trypost.core.data

import java.io.InputStream

/** A file the Owner picked or recorded, read as it uploads so a large file never sits in memory. */
interface UploadSource {
    val fileName: String
    val size: Long
    val mimeType: String

    fun open(): InputStream
}
