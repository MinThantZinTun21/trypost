package com.minthantzintun.trypost.feature.tasks

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.toggleable
import androidx.compose.material3.Icon
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.BodyBlock
import com.minthantzintun.trypost.core.ui.toAnnotatedString

/** The type a body block reads in, for a card or for the full Task. */
@Composable
fun BlockKind.textStyle(isCompact: Boolean): TextStyle = with(MaterialTheme.typography) {
    when (this@textStyle) {
        BlockKind.Heading1 -> if (isCompact) titleMedium else headlineSmall
        BlockKind.Heading2 -> if (isCompact) titleSmall else titleLarge
        else -> if (isCompact) bodyMedium else bodyLarge
    }
}

/**
 * A Task body's blocks with their headings, lists and checkboxes. With
 * [onToggle], each checkbox row ticks on a tap, passing its index among the
 * body's checkboxes; [checkboxOffset] is how many checkboxes come before
 * [blocks] in the body.
 */
@Composable
fun TaskBodyBlocks(
    blocks: List<BodyBlock>,
    isCompact: Boolean,
    modifier: Modifier = Modifier,
    onToggle: ((Int) -> Unit)? = null,
    checkboxOffset: Int = 0,
) {
    var checkboxIndex = checkboxOffset

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(if (isCompact) Spacing.xxs else Spacing.xs)) {
        blocks.forEach { block ->
            val index = if (block.kind == BlockKind.Checkbox) checkboxIndex++ else -1
            BodyLine(
                block = block,
                isCompact = isCompact,
                onToggle = if (index >= 0 && onToggle != null) ({ onToggle(index) }) else null,
                modifier = if (index >= 0) Modifier.testTag("task-checkbox-$index") else Modifier,
            )
        }
    }
}

@Composable
private fun BodyLine(block: BodyBlock, isCompact: Boolean, onToggle: (() -> Unit)?, modifier: Modifier = Modifier) {
    val style = block.kind.textStyle(isCompact)
    val maxLines = if (isCompact) 3 else Int.MAX_VALUE
    val text = block.text.toAnnotatedString()

    when (block.kind) {
        BlockKind.Heading1, BlockKind.Heading2 -> Text(
            text = text,
            style = style,
            fontWeight = FontWeight.SemiBold,
            maxLines = maxLines,
            overflow = TextOverflow.Ellipsis,
            modifier = modifier.padding(top = if (isCompact) 0.dp else Spacing.xs).semantics { heading() },
        )
        BlockKind.Paragraph -> Text(text = text, style = style, maxLines = maxLines, overflow = TextOverflow.Ellipsis, modifier = modifier)
        BlockKind.Bullet, BlockKind.Numbered -> Row(modifier = modifier) {
            Text(
                text = if (block.kind == BlockKind.Bullet) "•" else "${block.number}.",
                style = style,
                modifier = Modifier.width(if (isCompact) 18.dp else 24.dp),
            )
            Text(text = text, style = style, maxLines = maxLines, overflow = TextOverflow.Ellipsis)
        }
        BlockKind.Checkbox -> {
            val rowModifier = if (onToggle != null) {
                modifier
                    .fillMaxWidth()
                    .defaultMinSize(minHeight = IconSize.touchTarget)
                    .toggleable(value = block.isChecked, role = Role.Checkbox, onValueChange = { onToggle() })
            } else {
                modifier
            }
            val color = LocalContentColor.current.copy(alpha = if (block.isChecked) 0.6f else 1f)

            Row(modifier = rowModifier, verticalAlignment = Alignment.CenterVertically) {
                Box(modifier = Modifier.width(if (isCompact) 22.dp else 32.dp)) {
                    Icon(
                        painter = painterResource(if (block.isChecked) R.drawable.ic_square_check else R.drawable.ic_square),
                        contentDescription = null,
                        tint = color,
                        modifier = Modifier.size(if (isCompact) IconSize.xs else IconSize.sm),
                    )
                }
                Text(
                    text = text,
                    style = style.copy(textDecoration = if (block.isChecked) TextDecoration.LineThrough else null),
                    color = color,
                    maxLines = if (isCompact) 2 else Int.MAX_VALUE,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}
