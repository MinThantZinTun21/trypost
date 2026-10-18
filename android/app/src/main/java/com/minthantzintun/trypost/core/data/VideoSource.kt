package com.minthantzintun.trypost.core.data

import java.io.InputStream

/** A video the Owner picked, read as it uploads so a large file never sits in memory. */
interface VideoSource {
    val fileName: String
    val size: Long
    val mimeType: String

    fun open(): InputStream
}
