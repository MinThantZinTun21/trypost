package com.minthantzintun.trypost.feature.detail

import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ActionIconButton
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.canSendToTelegram
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message
import kotlinx.coroutines.launch

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

    ActionIconButton(
        icon = R.drawable.ic_telegram,
        label = stringResource(R.string.telegram_send),
        onClick = { isConfirming = true },
        isBusy = isSending,
        modifier = Modifier.testTag("send-to-telegram"),
    )

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

/**
 * What came of the last Send to Telegram, as a snackbar wherever the Owner
 * has scrolled: the server's word that it was queued, or why it was refused.
 * A refusal stays until dismissed. The result is cleared as it shows, so it
 * never replays.
 */
@Composable
fun TelegramResultSnackbar(notice: String?, error: ApiError?, snackbarHostState: SnackbarHostState, onShown: () -> Unit) {
    val scope = rememberCoroutineScope()
    val message = when {
        error != null -> ((error as? ApiError.Validation)?.fields?.values?.flatten()?.ifEmpty { null } ?: listOf(error.message()))
            .joinToString("\n")
        else -> notice
    }
    val isError = error != null

    LaunchedEffect(notice, error) {
        if (message != null) {
            onShown()
            scope.launch {
                snackbarHostState.showSnackbar(
                    message = message,
                    withDismissAction = isError,
                    duration = if (isError) SnackbarDuration.Indefinite else SnackbarDuration.Short,
                )
            }
        }
    }
}
