package com.minthantzintun.trypost.core.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.TryPostTheme
import com.minthantzintun.trypost.core.model.PlatformStatus
import com.minthantzintun.trypost.core.model.PostStatus

/** The web app's status colours: gray, blue, yellow, green, orange, red. */
@Composable
fun PostStatus.color(): Color = with(TryPostTheme.statusColors) {
    when (this@color) {
        PostStatus.Draft -> neutral
        PostStatus.Scheduled -> info
        PostStatus.Publishing -> warning
        PostStatus.Published -> success
        PostStatus.PartiallyPublished -> warning
        PostStatus.Failed -> danger
    }
}

@Composable
fun PostStatus.label(): String = stringResource(
    when (this) {
        PostStatus.Draft -> R.string.status_draft
        PostStatus.Scheduled -> R.string.status_scheduled
        PostStatus.Publishing -> R.string.status_publishing
        PostStatus.Published -> R.string.status_published
        PostStatus.PartiallyPublished -> R.string.status_partially_published
        PostStatus.Failed -> R.string.status_failed
    },
)

@Composable
fun PlatformStatus.color(): Color = with(TryPostTheme.statusColors) {
    when (this@color) {
        PlatformStatus.Pending -> neutral
        PlatformStatus.Publishing -> warning
        PlatformStatus.Retrying -> warning
        PlatformStatus.Published -> success
        PlatformStatus.Failed -> danger
    }
}

@Composable
fun PlatformStatus.label(): String = stringResource(
    when (this) {
        PlatformStatus.Pending -> R.string.status_pending
        PlatformStatus.Publishing -> R.string.status_publishing
        PlatformStatus.Retrying -> R.string.status_retrying
        PlatformStatus.Published -> R.string.status_published
        PlatformStatus.Failed -> R.string.status_failed
    },
)
