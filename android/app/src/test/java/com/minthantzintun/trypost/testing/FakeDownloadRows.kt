package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.download.DownloadRow
import com.minthantzintun.trypost.core.download.DownloadRows

/** The app's unfinished downloads as DownloadManager would list them. */
class FakeDownloadRows(var rows: List<DownloadRow> = emptyList()) : DownloadRows {
    override fun unfinished(): List<DownloadRow> = rows
}
