package com.minthantzintun.trypost.feature.tasks

import android.content.Context
import android.media.MediaRecorder
import android.net.Uri
import android.os.Build
import androidx.core.content.FileProvider
import java.io.File
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter

/** Files the camera and the voice recorder write before a Task attachment uploads them; the cache may clear them. */
object AttachmentFiles {
    private val Stamp: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyyMMdd-HHmmss")

    /** A new file such as `photo-20261019-143000.jpg` in the cache's attachments folder. */
    fun create(context: Context, prefix: String, extension: String): File {
        val folder = File(context.cacheDir, "attachments").apply { mkdirs() }

        return File(folder, "$prefix-${LocalDateTime.now().format(Stamp)}.$extension")
    }

    /** The FileProvider address the camera writes to and the upload reads from. */
    fun uri(context: Context, file: File): Uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file)
}

/** Records a voice note as AAC in an MPEG-4 file (`.m4a`), the format the server takes for audio. */
class VoiceRecorder(private val context: Context) {
    private var recorder: MediaRecorder? = null

    var file: File? = null
        private set

    /** Starts recording; false when the microphone cannot be used. */
    fun start(): Boolean {
        val target = AttachmentFiles.create(context, "voice", "m4a")
        val mediaRecorder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) MediaRecorder(context) else @Suppress("DEPRECATION") MediaRecorder()

        return runCatching {
            mediaRecorder.apply {
                setAudioSource(MediaRecorder.AudioSource.MIC)
                setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
                setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
                setAudioEncodingBitRate(96_000)
                setAudioSamplingRate(44_100)
                setOutputFile(target.absolutePath)
                prepare()
                start()
            }
            recorder = mediaRecorder
            file = target
        }.onFailure {
            mediaRecorder.release()
            target.delete()
        }.isSuccess
    }

    /** Stops and returns the recording; null when nothing usable was recorded. */
    fun stop(): File? {
        val mediaRecorder = recorder ?: return null
        recorder = null
        val stopped = runCatching { mediaRecorder.stop() }.isSuccess
        mediaRecorder.release()

        return file?.takeIf { stopped && it.length() > 0 } ?: run {
            file?.delete()
            null
        }
    }

    /** Stops and throws the recording away. */
    fun cancel() {
        stop()?.delete()
        file = null
    }
}
