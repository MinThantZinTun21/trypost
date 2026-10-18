package com.minthantzintun.trypost

import android.app.Application
import com.minthantzintun.trypost.core.download.DownloadNotifications
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class TryPostApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        DownloadNotifications.createChannel(this)
    }
}
