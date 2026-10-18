package com.minthantzintun.trypost.feature.detail

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.canSendToTelegram
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message

/**
 * Send to Telegram (ADR 0012) in Post detail's top bar: copies a Post with a
 * video or photo to the Telegram channel after a confirm, in any status. The
 * Post does not change.
 */
@Composable
fun TelegramButton(post: Post, isSending: Boolean, onSend: () -> Unit) {
    if (!post.canSendToTelegram) {
        return
    }

    var isConfirming by rememberSaveable { mutableStateOf(false) }

    Box(contentAlignment = Alignment.Center) {
        IconButton(
            onClick = { isConfirming = true },
            enabled = !isSending,
            modifier = Modifier.testTag("send-to-telegram"),
        ) {
            if (isSending) {
                CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
            } else {
                Icon(painterResource(R.drawable.ic_telegram), contentDescription = stringResource(R.string.telegram_send))
            }
        }
    }

    if (isConfirming) {
        ConfirmDialog(
            title = stringResource(R.string.telegram_send_confirm_title),
            body = stringResource(R.string.telegram_send_confirm_body),
            confirmLabel = stringResource(R.string.telegram_send_confirm),
            onConfirm = {
                isConfirming = false
                onSend()
            },
            onDismiss = { isConfirming = false },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_telegram,
            confirmTestTag = "send-to-telegram-confirm",
        )
    }
}

/** What came of the last Send to Telegram: the server's word that it was queued, or why it was refused. */
@Composable
fun TelegramMessages(notice: String?, error: ApiError?) {
    if (notice != null) {
        Notice(
            icon = R.drawable.ic_telegram,
            tint = MaterialTheme.colorScheme.primary,
            lines = listOf(notice),
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            testTag = "telegram-notice",
        )
    }

    if (error != null) {
        Notice(
            icon = R.drawable.ic_status_failed,
            tint = MaterialTheme.colorScheme.error,
            lines = (error as? ApiError.Validation)?.fields?.values?.flatten()?.ifEmpty { null } ?: listOf(error.message()),
            color = MaterialTheme.colorScheme.error,
            testTag = "telegram-error",
        )
    }
}

@Composable
private fun Notice(icon: Int, tint: Color, lines: List<String>, color: Color, testTag: String) {
    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), modifier = Modifier.testTag(testTag)) {
        Icon(painterResource(icon), contentDescription = null, tint = tint, modifier = Modifier.size(IconSize.sm))
        Column {
            lines.forEach { Text(text = it, style = MaterialTheme.typography.bodySmall, color = color) }
        }
    }
}
