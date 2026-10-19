package com.minthantzintun.trypost.feature.tasks

import android.Manifest
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.core.net.toUri
import coil3.compose.AsyncImage
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.data.AttachmentUpload
import com.minthantzintun.trypost.core.data.ContentUriUploadSource
import com.minthantzintun.trypost.core.data.UploadSource
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ButtonLabel
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.AttachmentType
import com.minthantzintun.trypost.core.model.TaskAttachment
import com.minthantzintun.trypost.core.model.TimerClock
import com.minthantzintun.trypost.core.ui.message
import kotlinx.coroutines.delay
import java.io.File

private val TileSize = 104.dp

/** What the attachments section can ask of the Task. */
data class AttachmentActions(
    val onAttach: (UploadSource, AttachmentType) -> Unit,
    val onRetryUpload: (Long) -> Unit,
    val onDismissUpload: (Long) -> Unit,
    val onDelete: (String) -> Unit,
)

/**
 * The Task's photos, videos and voice notes, then the ones uploading. Add
 * picks from the Photo Picker, takes a photo or video with the camera, or
 * records a voice note. A tap opens the file in the phone's viewer or
 * player; a long press deletes it.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun AttachmentsSection(
    attachments: List<TaskAttachment>,
    uploads: List<AttachmentUpload>,
    deletingId: String?,
    actions: AttachmentActions,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    var confirmingDelete by remember { mutableStateOf<TaskAttachment?>(null) }
    var isRecording by rememberSaveable { mutableStateOf(false) }

    Column(modifier = modifier.testTag("task-attachments"), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        if (attachments.isNotEmpty() || uploads.isNotEmpty()) {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                attachments.forEach { attachment ->
                    AttachmentTile(
                        attachment = attachment,
                        isDeleting = attachment.id == deletingId,
                        onOpen = { context.openAttachment(attachment) },
                        onDelete = { confirmingDelete = attachment },
                    )
                }
                uploads.forEach { upload ->
                    UploadTile(upload, onRetry = { actions.onRetryUpload(upload.id) }, onDismiss = { actions.onDismissUpload(upload.id) })
                }
            }
        }
        AddAttachmentButton(onAttach = actions.onAttach, onRecordVoice = { isRecording = true })
    }

    if (isRecording) {
        VoiceNoteDialog(
            onRecorded = { file ->
                isRecording = false
                val uri = AttachmentFiles.uri(context, file)
                actions.onAttach(ContentUriUploadSource(context.contentResolver, uri, "audio/mp4"), AttachmentType.Audio)
            },
            onDismiss = { isRecording = false },
        )
    }

    confirmingDelete?.let { attachment ->
        ConfirmDialog(
            title = stringResource(R.string.attachment_delete_title),
            body = stringResource(R.string.attachment_delete_body),
            confirmLabel = stringResource(R.string.delete),
            onConfirm = {
                confirmingDelete = null
                actions.onDelete(attachment.id)
            },
            onDismiss = { confirmingDelete = null },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_delete,
            isDestructive = true,
            confirmTestTag = "attachment-delete-confirm",
        )
    }
}

@Composable
private fun AddAttachmentButton(onAttach: (UploadSource, AttachmentType) -> Unit, onRecordVoice: () -> Unit) {
    val context = LocalContext.current
    var isMenuOpen by remember { mutableStateOf(false) }
    // The camera writes here; kept across the camera's round trip, which may recreate this screen.
    var cameraFile by rememberSaveable { mutableStateOf<String?>(null) }

    val attachUri = { uri: Uri, fallbackMimeType: String ->
        val source = ContentUriUploadSource(context.contentResolver, uri, fallbackMimeType)
        val type = AttachmentType.fromMimeType(source.mimeType)
        if (type == null) {
            Toast.makeText(context, R.string.attachment_unsupported, Toast.LENGTH_LONG).show()
        } else {
            onAttach(source, type)
        }
    }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        uri?.let { attachUri(it, "image/jpeg") }
    }
    val takePhoto = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { isSaved ->
        cameraFile?.let { File(it) }?.takeIf { isSaved && it.length() > 0 }?.let { attachUri(AttachmentFiles.uri(context, it), "image/jpeg") }
        cameraFile = null
    }
    val recordVideo = rememberLauncherForActivityResult(ActivityResultContracts.CaptureVideo()) { isSaved ->
        cameraFile?.let { File(it) }?.takeIf { isSaved && it.length() > 0 }?.let { attachUri(AttachmentFiles.uri(context, it), "video/mp4") }
        cameraFile = null
    }
    val microphone = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { isGranted ->
        if (isGranted) {
            onRecordVoice()
        } else {
            Toast.makeText(context, R.string.attachment_needs_microphone, Toast.LENGTH_LONG).show()
        }
    }

    val launchCamera = { prefix: String, extension: String, launch: (Uri) -> Unit ->
        val file = AttachmentFiles.create(context, prefix, extension)
        cameraFile = file.absolutePath
        try {
            launch(AttachmentFiles.uri(context, file))
        } catch (exception: ActivityNotFoundException) {
            cameraFile = null
            Toast.makeText(context, R.string.attachment_no_camera, Toast.LENGTH_LONG).show()
        }
    }

    Box {
        OutlinedButton(
            onClick = { isMenuOpen = true },
            contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
            modifier = Modifier.testTag("attachment-add"),
        ) {
            ButtonLabel(icon = R.drawable.ic_paperclip, label = stringResource(R.string.attachment_add))
        }
        DropdownMenu(expanded = isMenuOpen, onDismissRequest = { isMenuOpen = false }) {
            AddMenuItem(R.drawable.ic_photo, R.string.attachment_pick, "attachment-pick") {
                isMenuOpen = false
                picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageAndVideo))
            }
            AddMenuItem(R.drawable.ic_camera, R.string.attachment_take_photo, "attachment-take-photo") {
                isMenuOpen = false
                launchCamera("photo", "jpg") { takePhoto.launch(it) }
            }
            AddMenuItem(R.drawable.ic_video, R.string.attachment_record_video, "attachment-record-video") {
                isMenuOpen = false
                launchCamera("video", "mp4") { recordVideo.launch(it) }
            }
            AddMenuItem(R.drawable.ic_microphone, R.string.attachment_voice_note, "attachment-voice-note") {
                isMenuOpen = false
                if (ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
                    onRecordVoice()
                } else {
                    microphone.launch(Manifest.permission.RECORD_AUDIO)
                }
            }
        }
    }
}

@Composable
private fun AddMenuItem(icon: Int, label: Int, testTag: String, onClick: () -> Unit) {
    DropdownMenuItem(
        text = { Text(stringResource(label)) },
        leadingIcon = { Icon(painterResource(icon), contentDescription = null) },
        onClick = onClick,
        modifier = Modifier.testTag(testTag),
    )
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun AttachmentTile(attachment: TaskAttachment, isDeleting: Boolean, onOpen: () -> Unit, onDelete: () -> Unit) {
    val name = attachment.fileName ?: stringResource(attachment.type.labelRes())
    val openLabel = stringResource(R.string.attachment_open)
    val deleteLabel = stringResource(R.string.delete)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(TileSize)
            .clip(MaterialTheme.shapes.medium)
            .background(MaterialTheme.colorScheme.surfaceVariant)
            .combinedClickable(
                enabled = !isDeleting,
                onClickLabel = openLabel,
                onLongClickLabel = deleteLabel,
                onLongClick = onDelete,
                onClick = onOpen,
            )
            .semantics { contentDescription = name }
            .testTag("attachment-${attachment.id}"),
    ) {
        when (attachment.type) {
            AttachmentType.Image -> AsyncImage(
                model = attachment.url,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
            AttachmentType.Video, AttachmentType.Audio -> Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(Spacing.xxs),
                modifier = Modifier.padding(Spacing.xs),
            ) {
                Icon(
                    painter = painterResource(if (attachment.type == AttachmentType.Video) R.drawable.ic_video else R.drawable.ic_headphones),
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(IconSize.md),
                )
                Text(
                    text = attachment.durationSeconds?.let { TimerClock.format(it.toLong()) } ?: name,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    textAlign = TextAlign.Center,
                )
            }
        }
        if (isDeleting) {
            CircularProgressIndicator(modifier = Modifier.size(IconSize.md), strokeWidth = 2.dp)
        }
    }
}

@Composable
private fun UploadTile(upload: AttachmentUpload, onRetry: () -> Unit, onDismiss: () -> Unit) {
    val error = upload.error

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(TileSize)
            .clip(MaterialTheme.shapes.medium)
            .background(if (error == null) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.errorContainer)
            .testTag("attachment-upload-${upload.id}"),
    ) {
        if (error == null) {
            Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                CircularProgressIndicator(progress = { upload.progress }, modifier = Modifier.size(IconSize.md), strokeWidth = 2.dp)
                Text(
                    text = stringResource(R.string.attachment_uploading, (upload.progress * 100).toInt()),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        } else {
            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(Spacing.xxs)) {
                Text(
                    text = error.message(),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onErrorContainer,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.testTag("attachment-upload-error"),
                )
                Row {
                    ActionIconButton(icon = R.drawable.ic_repeat, label = stringResource(R.string.retry), onClick = onRetry)
                    ActionIconButton(icon = R.drawable.ic_close, label = stringResource(R.string.attachment_dismiss), onClick = onDismiss)
                }
            }
        }
    }
}

/** Records a voice note while open, showing how long it has run; Attach stops and uploads it. */
@Composable
private fun VoiceNoteDialog(onRecorded: (File) -> Unit, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val recorder = remember { VoiceRecorder(context) }
    var isRecording by remember { mutableStateOf(false) }
    var hasFailed by remember { mutableStateOf(false) }
    var seconds by remember { mutableLongStateOf(0L) }

    DisposableEffect(recorder) {
        isRecording = recorder.start()
        hasFailed = !isRecording
        onDispose { recorder.cancel() }
    }
    LaunchedEffect(isRecording) {
        val startedAt = System.currentTimeMillis()
        while (isRecording) {
            seconds = (System.currentTimeMillis() - startedAt) / 1_000
            delay(500)
        }
    }

    AlertDialog(
        onDismissRequest = {},
        icon = { Icon(painterResource(R.drawable.ic_microphone), contentDescription = null) },
        title = { Text(stringResource(R.string.attachment_voice_note)) },
        text = {
            Text(
                text = if (hasFailed) stringResource(R.string.attachment_recording_failed) else TimerClock.format(seconds),
                style = if (hasFailed) MaterialTheme.typography.bodyMedium else MaterialTheme.typography.headlineMedium,
                modifier = Modifier.testTag("voice-note-time"),
            )
        },
        confirmButton = {
            Button(
                onClick = {
                    isRecording = false
                    val file = recorder.stop()
                    if (file == null) {
                        hasFailed = true
                    } else {
                        onRecorded(file)
                    }
                },
                enabled = isRecording,
                modifier = Modifier.testTag("voice-note-attach"),
            ) { Text(stringResource(R.string.attachment_attach)) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}

private fun AttachmentType.labelRes(): Int = when (this) {
    AttachmentType.Image -> R.string.attachment_photo
    AttachmentType.Video -> R.string.attachment_video
    AttachmentType.Audio -> R.string.attachment_voice_note
}

/** Opens the file in whatever the phone uses for it: the gallery, the video player, the music app. */
private fun Context.openAttachment(attachment: TaskAttachment) {
    val url = attachment.url ?: return
    val intent = Intent(Intent.ACTION_VIEW)
        .setDataAndType(url.toUri(), attachment.mimeType ?: "${attachment.type.wire}/*")
        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    try {
        startActivity(intent)
    } catch (exception: ActivityNotFoundException) {
        Toast.makeText(this, R.string.attachment_no_app, Toast.LENGTH_LONG).show()
    }
}
