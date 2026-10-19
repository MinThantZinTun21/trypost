package com.minthantzintun.trypost.core.designsystem

import androidx.annotation.DrawableRes
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.PlainTooltip
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TooltipAnchorPosition
import androidx.compose.material3.TooltipBox
import androidx.compose.material3.TooltipDefaults
import androidx.compose.material3.rememberTooltipState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.colorResource
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.model.Platform

/** A Social account's network as its own logo, named for TalkBack. */
@Composable
fun PlatformLogo(platform: Platform, modifier: Modifier = Modifier, size: Dp = IconSize.sm, describe: Boolean = true) {
    val (icon, tint) = when (platform) {
        Platform.Facebook -> R.drawable.ic_logo_facebook to FacebookBlue
        Platform.TikTok -> R.drawable.ic_logo_tiktok to MaterialTheme.colorScheme.onSurface
        Platform.YouTube -> R.drawable.ic_logo_youtube to YouTubeRed
    }

    Icon(
        painter = painterResource(icon),
        contentDescription = if (describe) platform.label else null,
        tint = tint,
        modifier = modifier.size(size),
    )
}

/**
 * A status as a pill: an icon, a word and a tone, so colour is never the
 * only sign. TalkBack reads the word once.
 */
@Composable
fun StatusPill(label: String, @DrawableRes icon: Int, tone: Tone, modifier: Modifier = Modifier) {
    Surface(
        shape = CircleShape,
        color = tone.container,
        contentColor = tone.content,
        modifier = modifier.semantics(mergeDescendants = true) {},
    ) {
        Row(
            modifier = Modifier.padding(start = Spacing.sm, end = Spacing.md, top = Spacing.xs, bottom = Spacing.xs),
            horizontalArrangement = Arrangement.spacedBy(Spacing.xs),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(IconSize.xs))
            Text(text = label, style = MaterialTheme.typography.labelMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

/** An icon in a tonal circle: the head of empty and error states. */
@Composable
private fun IconBadge(@DrawableRes icon: Int, modifier: Modifier = Modifier, isError: Boolean = false) {
    val colors = MaterialTheme.colorScheme

    Box(
        modifier = modifier
            .size(72.dp)
            .background(if (isError) colors.errorContainer else colors.secondaryContainer, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            painter = painterResource(icon),
            contentDescription = null,
            tint = if (isError) colors.onErrorContainer else colors.onSecondaryContainer,
            modifier = Modifier.size(32.dp),
        )
    }
}

/** A centred message with an icon and an optional action: empty lists and errors. */
@Composable
fun StateMessage(
    @DrawableRes icon: Int,
    title: String,
    body: String,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
    isError: Boolean = false,
) {
    Column(
        modifier = modifier.fillMaxWidth().padding(horizontal = Spacing.xxl, vertical = Spacing.xxl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(Spacing.md),
    ) {
        IconBadge(icon = icon, isError = isError)
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            textAlign = TextAlign.Center,
            modifier = Modifier.semantics { heading() },
        )
        Text(
            text = body,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.widthIn(max = 360.dp),
        )
        if (actionLabel != null && onAction != null) {
            if (isError) {
                Button(onClick = onAction, modifier = Modifier.padding(top = Spacing.sm)) { Text(actionLabel) }
            } else {
                FilledTonalButton(onClick = onAction, modifier = Modifier.padding(top = Spacing.sm)) { Text(actionLabel) }
            }
        }
    }
}

/**
 * A placeholder block that gently pulses while content loads. It holds the
 * space the content takes, so nothing jumps when it arrives.
 */
@Composable
fun Skeleton(modifier: Modifier = Modifier, shape: Shape = MaterialTheme.shapes.extraSmall) {
    val transition = rememberInfiniteTransition(label = "skeleton")
    val alpha by transition.animateFloat(
        initialValue = 0.5f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(durationMillis = 900), RepeatMode.Reverse),
        label = "skeleton-alpha",
    )

    Box(
        modifier = modifier
            .graphicsLayer { this.alpha = alpha }
            .background(MaterialTheme.colorScheme.surfaceContainerHighest, shape)
            .clearAndSetSemantics {},
    )
}

/** A section title with an optional trailing control, read as a heading. */
@Composable
fun SectionHeader(title: String, modifier: Modifier = Modifier, trailing: (@Composable () -> Unit)? = null) {
    Row(
        modifier = modifier.fillMaxWidth().heightIn(min = IconSize.touchTarget),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.weight(1f).semantics { heading() },
        )
        trailing?.invoke()
    }
}

/**
 * The confirm every irreversible action asks: the action first, Cancel
 * second. A destructive confirm is red.
 */
@Composable
fun ConfirmDialog(
    title: String,
    body: String,
    confirmLabel: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    dismissLabel: String,
    modifier: Modifier = Modifier,
    @DrawableRes icon: Int? = null,
    isDestructive: Boolean = false,
    confirmTestTag: String? = null,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = icon?.let { { Icon(painterResource(it), contentDescription = null) } },
        title = { Text(title, textAlign = if (icon != null) TextAlign.Center else null) },
        text = { Text(body) },
        confirmButton = {
            Button(
                onClick = onConfirm,
                colors = if (isDestructive) {
                    ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.error,
                        contentColor = MaterialTheme.colorScheme.onError,
                    )
                } else {
                    ButtonDefaults.buttonColors()
                },
                modifier = if (confirmTestTag != null) Modifier.testTag(confirmTestTag) else Modifier,
            ) { Text(confirmLabel) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(dismissLabel) } },
        modifier = modifier,
    )
}

/** The app's icon on its own lime tile, as the launcher shows it: the head of Sign in. */
@Composable
fun AppMark(modifier: Modifier = Modifier, size: Dp = 72.dp) {
    Image(
        painter = painterResource(R.drawable.ic_launcher_foreground),
        contentDescription = null,
        modifier = modifier
            .size(size)
            .background(colorResource(R.color.lime), MaterialTheme.shapes.large),
    )
}

/** A tonal block that groups related content: a card, a settings group, one part of a Post. */
@Composable
fun Panel(modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        modifier = modifier.fillMaxWidth(),
        content = content,
    )
}

/** An error next to what it is about: a red mark, an optional title, then each line. */
@Composable
fun ErrorMessage(lines: List<String>, modifier: Modifier = Modifier, title: String? = null) {
    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), modifier = modifier) {
        Icon(
            painter = painterResource(R.drawable.ic_status_failed),
            contentDescription = null,
            tint = MaterialTheme.colorScheme.error,
            modifier = Modifier.size(IconSize.sm),
        )
        Column {
            title?.let { Text(text = it, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.error) }
            lines.forEach { Text(text = it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error) }
        }
    }
}

/** A button's content: its icon, or a spinner while its action runs, then its label. */
@Composable
fun RowScope.ButtonLabel(@DrawableRes icon: Int, label: String, isBusy: Boolean = false) {
    if (isBusy) {
        CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp, color = LocalContentColor.current)
    } else {
        Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(IconSize.sm))
    }
    Text(label, modifier = Modifier.padding(start = Spacing.sm))
}

/**
 * An icon-only action that names itself: a tooltip on long press, and
 * [label] for TalkBack, even while it spins.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ActionIconButton(
    @DrawableRes icon: Int,
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    isBusy: Boolean = false,
) {
    TooltipBox(
        positionProvider = TooltipDefaults.rememberTooltipPositionProvider(TooltipAnchorPosition.Below),
        tooltip = { PlainTooltip { Text(label) } },
        state = rememberTooltipState(),
    ) {
        IconButton(
            onClick = onClick,
            enabled = enabled && !isBusy,
            modifier = modifier.semantics { contentDescription = label },
        ) {
            if (isBusy) {
                CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
            } else {
                Icon(painterResource(icon), contentDescription = null)
            }
        }
    }
}
