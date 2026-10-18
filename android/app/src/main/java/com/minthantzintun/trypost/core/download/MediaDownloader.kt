package com.minthantzintun.trypost.core.download

import com.minthantzintun.trypost.core.model.DownloadLink

enum class DownloadOutcome { Started, Failed }

/** Saves a file to the phone's Downloads; the system shows its progress. */
interface MediaDownloader {
    fun start(link: DownloadLink): DownloadOutcome
}
