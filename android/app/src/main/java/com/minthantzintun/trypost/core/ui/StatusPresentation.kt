package com.minthantzintun.trypost.core.ui

import androidx.annotation.DrawableRes
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.StatusPill
import com.minthantzintun.trypost.core.designsystem.Tone
import com.minthantzintun.trypost.core.designsystem.TryPostTheme
import com.minthantzintun.trypost.core.model.PlatformStatus
import com.minthantzintun.trypost.core.model.PostStatus

/** The web app's status meanings: blue scheduled, amber going out, green done, red failed. */
@Composable
fun PostStatus.tone(): Tone = with(TryPostTheme.statusColors) {
    when (this@tone) {
        PostStatus.Scheduled -> scheduled
        PostStatus.Publishing -> publishing
        PostStatus.Published -> published
        PostStatus.PartiallyPublished -> publishing
        PostStatus.Failed -> failed
    }
}

@DrawableRes
fun PostStatus.icon(): Int = when (this) {
    PostStatus.Scheduled -> R.drawable.ic_status_scheduled
    PostStatus.Publishing -> R.drawable.ic_status_publishing
    PostStatus.Published -> R.drawable.ic_status_published
    PostStatus.PartiallyPublished -> R.drawable.ic_status_partial
    PostStatus.Failed -> R.drawable.ic_status_failed
}

@Composable
fun PostStatus.label(): String = stringResource(
    when (this) {
        PostStatus.Scheduled -> R.string.status_scheduled
        PostStatus.Publishing -> R.string.status_publishing
        PostStatus.Published -> R.string.status_published
        PostStatus.PartiallyPublished -> R.string.status_partially_published
        PostStatus.Failed -> R.string.status_failed
    },
)

@Composable
fun PlatformStatus.tone(): Tone = with(TryPostTheme.statusColors) {
    when (this@tone) {
        PlatformStatus.Pending -> neutral
        PlatformStatus.Publishing -> publishing
        PlatformStatus.Retrying -> publishing
        PlatformStatus.Published -> published
        PlatformStatus.Failed -> failed
    }
}

@DrawableRes
fun PlatformStatus.icon(): Int = when (this) {
    PlatformStatus.Pending -> R.drawable.ic_status_pending
    PlatformStatus.Publishing -> R.drawable.ic_status_publishing
    PlatformStatus.Retrying -> R.drawable.ic_status_retrying
    PlatformStatus.Published -> R.drawable.ic_status_published
    PlatformStatus.Failed -> R.drawable.ic_status_failed
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

@Composable
fun PostStatusPill(status: PostStatus, modifier: Modifier = Modifier) {
    StatusPill(label = status.label(), icon = status.icon(), tone = status.tone(), modifier = modifier)
}

@Composable
fun PlatformStatusPill(status: PlatformStatus, modifier: Modifier = Modifier) {
    StatusPill(label = status.label(), icon = status.icon(), tone = status.tone(), modifier = modifier)
}
