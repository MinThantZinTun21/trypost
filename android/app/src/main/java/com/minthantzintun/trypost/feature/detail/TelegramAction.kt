package com.minthantzintun.trypost.feature.detail

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.canSendToTelegram
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message

/**
 * Send to Telegram (ADR 0012): copies a Post with a video or photo to the
 * Telegram channel after a confirm, in any status. The Post does not change.
 */
@Composable
fun TelegramAction(
    post: Post,
    isSending: Boolean,
    notice: String?,
    error: ApiError?,
    onSend: () -> Unit,
) {
    if (!post.canSendToTelegram) {
        return
    }

    var isConfirming by rememberSaveable { mutableStateOf(false) }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedButton(
                onClick = { isConfirming = true },
                enabled = !isSending,
                modifier = Modifier.testTag("send-to-telegram"),
            ) {
                Text(stringResource(R.string.telegram_send))
            }
            if (isSending) {
                CircularProgressIndicator(modifier = Modifier.size(24.dp), strokeWidth = 2.dp)
            }
        }

        if (notice != null) {
            Text(
                text = notice,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.testTag("telegram-notice"),
            )
        }

        if (error != null) {
            val lines = (error as? ApiError.Validation)?.fields?.values?.flatten()?.ifEmpty { null } ?: listOf(error.message())

            Column(modifier = Modifier.testTag("telegram-error")) {
                lines.forEach {
                    Text(text = it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }

    if (isConfirming) {
        AlertDialog(
            onDismissRequest = { isConfirming = false },
            title = { Text(stringResource(R.string.telegram_send_confirm_title)) },
            text = { Text(stringResource(R.string.telegram_send_confirm_body)) },
            confirmButton = {
                TextButton(
                    onClick = {
                        isConfirming = false
                        onSend()
                    },
                    modifier = Modifier.testTag("send-to-telegram-confirm"),
                ) { Text(stringResource(R.string.telegram_send_confirm)) }
            },
            dismissButton = {
                TextButton(onClick = { isConfirming = false }) { Text(stringResource(R.string.cancel)) }
            },
        )
    }
}
