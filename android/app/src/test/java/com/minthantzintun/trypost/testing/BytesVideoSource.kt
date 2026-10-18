package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.data.VideoSource
import java.io.ByteArrayInputStream
import java.io.InputStream

/** A picked video held in memory. */
class BytesVideoSource(
    override val fileName: String = "Beach day.mp4",
    private val bytes: ByteArray = ByteArray(200_000) { (it % 251).toByte() },
    override val mimeType: String = "video/mp4",
) : VideoSource {
    override val size: Long get() = bytes.size.toLong()

    override fun open(): InputStream = ByteArrayInputStream(bytes)
}
