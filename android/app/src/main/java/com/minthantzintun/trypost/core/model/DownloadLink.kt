package com.minthantzintun.trypost.core.model

/**
 * Where a file downloads from and the name it is saved under. The URL is the
 * server's signed Download link, which needs no token.
 */
data class DownloadLink(
    val url: String,
    val fileName: String,
    val mimeType: String?,
)
