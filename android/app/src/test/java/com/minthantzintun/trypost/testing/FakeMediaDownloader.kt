package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.download.MediaDownloader
import com.minthantzintun.trypost.core.model.DownloadLink

/** Records what the app asked to download; [accepts] false plays a refused download. */
class FakeMediaDownloader(var accepts: Boolean = true) : MediaDownloader {
    val started = mutableListOf<DownloadLink>()

    override fun start(link: DownloadLink): DownloadOutcome {
        if (!accepts) {
            return DownloadOutcome.Failed
        }

        started += link

        return DownloadOutcome.Started
    }
}
