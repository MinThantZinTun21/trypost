package com.minthantzintun.trypost.core.designsystem

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.core.model.Platform

/** An uppercase, tracked label: the web app's badges and buttons. */
fun String.asLabel(): String = uppercase()

@Composable
fun StatusBadge(label: String, color: Color, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        color = color.copy(alpha = 0.12f),
        contentColor = color,
        border = BorderStroke(1.dp, color.copy(alpha = 0.4f)),
    ) {
        Text(
            text = label.asLabel(),
            style = MaterialTheme.typography.labelSmall,
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
        )
    }
}

/** A Social account's network, as a small square tag. */
@Composable
fun PlatformTag(platform: Platform, modifier: Modifier = Modifier) {
    val color = when (platform) {
        Platform.Facebook -> Color(0xFF1877F2)
        Platform.TikTok -> MaterialTheme.colorScheme.onSurface
        Platform.YouTube -> Color(0xFFFF0033)
    }
    val text = when (platform) {
        Platform.Facebook -> "FB"
        Platform.TikTok -> "TT"
        Platform.YouTube -> "YT"
    }

    Surface(modifier = modifier, color = color, contentColor = MaterialTheme.colorScheme.surface) {
        Text(
            text = text,
            style = MaterialTheme.typography.labelSmall,
            modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp),
        )
    }
}

/** A centred message with an optional action: empty lists and errors. */
@Composable
fun MessageState(
    title: String,
    body: String,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
    isError: Boolean = false,
) {
    Column(
        modifier = modifier.fillMaxWidth().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            color = if (isError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface,
            textAlign = TextAlign.Center,
        )
        Text(
            text = body,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
        if (actionLabel != null && onAction != null) {
            if (isError) {
                Button(onClick = onAction, modifier = Modifier.padding(top = 8.dp)) { Text(actionLabel.asLabel()) }
            } else {
                OutlinedButton(onClick = onAction, modifier = Modifier.padding(top = 8.dp)) { Text(actionLabel.asLabel()) }
            }
        }
    }
}
