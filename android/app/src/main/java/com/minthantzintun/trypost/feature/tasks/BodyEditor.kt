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
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.TextRange
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TextFieldValue
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.RichText
import com.minthantzintun.trypost.core.ui.toAnnotatedString

/**
 * Sits before each block's text so Backspace at the start still reaches the
 * field: deleting it is "Backspace at the start".
 */
private const val Sentinel = '​'

/** What the body editor reports back. */
data class BodyEditorActions(
    val onTextChange: (blockId: Long, text: String, start: Int, end: Int) -> Unit,
    val onSelect: (blockId: Long, start: Int, end: Int) -> Unit,
    val onBackspaceAtStart: (blockId: Long) -> Unit,
    val onToggleChecked: (blockId: Long) -> Unit,
)

/**
 * The Task body as a field per block: headings, lists and checkboxes look
 * as they read, with no Markdown marks to type or see.
 */
@Composable
fun BodyEditor(
    body: BodyEditorState,
    actions: BodyEditorActions,
    enabled: Boolean,
    modifier: Modifier = Modifier,
) {
    val focusRequesters = remember { mutableMapOf<Long, FocusRequester>() }
    val numbers = body.numbers

    LaunchedEffect(body.focusRequest) {
        if (body.focusRequest > 0) {
            body.selection?.let { focusRequesters[it.blockId]?.requestFocus() }
        }
    }

    Column(modifier = modifier.testTag("task-body"), verticalArrangement = Arrangement.spacedBy(Spacing.xxs)) {
        body.blocks.forEachIndexed { index, block ->
            key(block.id) {
                val focusRequester = remember { FocusRequester() }
                DisposableEffect(focusRequester) {
                    focusRequesters[block.id] = focusRequester
                    onDispose { focusRequesters.remove(block.id) }
                }

                BlockRow(
                    block = block,
                    number = numbers[index],
                    selection = body.selection?.takeIf { it.blockId == block.id },
                    placeholder = if (body.blocks.size == 1 && block.kind == BlockKind.Paragraph) stringResource(R.string.task_body_placeholder) else null,
                    actions = actions,
                    enabled = enabled,
                    modifier = Modifier.testTag("task-block-$index"),
                    fieldModifier = Modifier.focusRequester(focusRequester).testTag("task-block-field-$index"),
                )
            }
        }
    }
}

@Composable
private fun BlockRow(
    block: EditorBlock,
    number: Int,
    selection: EditorSelection?,
    placeholder: String?,
    actions: BodyEditorActions,
    enabled: Boolean,
    modifier: Modifier,
    fieldModifier: Modifier,
) {
    val isChecked = block.kind == BlockKind.Checkbox && block.isChecked
    val contentColor = LocalContentColor.current.copy(alpha = if (isChecked) 0.6f else 1f)
    val style = block.kind.textStyle(isCompact = false).let { base ->
        when (block.kind) {
            BlockKind.Heading1, BlockKind.Heading2 -> base.copy(fontWeight = FontWeight.SemiBold)
            else -> base
        }
    }.copy(color = contentColor, textDecoration = if (isChecked) TextDecoration.LineThrough else null)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(top = if (block.kind == BlockKind.Heading1 || block.kind == BlockKind.Heading2) Spacing.xs else 0.dp),
        verticalAlignment = if (block.kind == BlockKind.Checkbox) Alignment.CenterVertically else Alignment.Top,
    ) {
        when (block.kind) {
            BlockKind.Checkbox -> {
                val label = stringResource(R.string.task_format_checkbox)
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .size(IconSize.touchTarget)
                        .toggleable(value = block.isChecked, enabled = enabled, role = Role.Checkbox, onValueChange = { actions.onToggleChecked(block.id) })
                        .semantics { contentDescription = label }
                        .testTag("task-block-check-${block.id}"),
                ) {
                    Icon(
                        painter = painterResource(if (block.isChecked) R.drawable.ic_square_check else R.drawable.ic_square),
                        contentDescription = null,
                        tint = contentColor,
                        modifier = Modifier.size(IconSize.sm),
                    )
                }
            }
            BlockKind.Bullet, BlockKind.Numbered -> Text(
                text = if (block.kind == BlockKind.Bullet) "•" else "$number.",
                style = style,
                modifier = Modifier.width(28.dp),
            )
            else -> Unit
        }

        BlockField(
            block = block,
            selection = selection,
            placeholder = placeholder,
            style = style,
            actions = actions,
            enabled = enabled,
            modifier = Modifier
                .weight(1f)
                .defaultMinSize(minHeight = if (block.kind == BlockKind.Checkbox) IconSize.touchTarget else 0.dp)
                .then(fieldModifier),
        )
    }
}

@Composable
private fun BlockField(
    block: EditorBlock,
    selection: EditorSelection?,
    placeholder: String?,
    style: TextStyle,
    actions: BodyEditorActions,
    enabled: Boolean,
    modifier: Modifier,
) {
    var value by remember(block.id) { mutableStateOf(fieldValue(block.text.text, selection)) }
    var syncedText by remember(block.id) { mutableStateOf(block.text.text) }

    // A split, a join or a paste changed the text from outside the field. The
    // field's own typing reaches the editor a frame later, so only a new text
    // from the editor that the field does not show yet resets it.
    if (block.text.text != syncedText) {
        syncedText = block.text.text
        if (value.text.removePrefix(Sentinel.toString()) != block.text.text) {
            value = fieldValue(block.text.text, selection)
        }
    }

    val transformation = remember(block.text) { SpanTransformation(block.text) }

    Box(modifier = modifier, contentAlignment = Alignment.CenterStart) {
        if (placeholder != null && block.text.text.isEmpty()) {
            Text(placeholder, style = style.copy(color = MaterialTheme.colorScheme.onSurfaceVariant))
        }
        BasicTextField(
            value = value,
            onValueChange = { next -> value = next.reportTo(block, value, actions) },
            enabled = enabled,
            textStyle = style,
            cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            visualTransformation = transformation,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

/** The field's value for [text]: the sentinel, then the text, with the cursor where the editor has it. */
private fun fieldValue(text: String, selection: EditorSelection?): TextFieldValue {
    val start = (selection?.start ?: text.length).coerceIn(0, text.length) + 1
    val end = (selection?.end ?: text.length).coerceIn(0, text.length) + 1

    return TextFieldValue("$Sentinel$text", TextRange(start, end))
}

/**
 * Tells the editor what the field did and returns the value to keep: a
 * deleted sentinel is Backspace at the start; a new line splits the block;
 * anything else is the text without the sentinel, cursor shifted back by one.
 */
private fun TextFieldValue.reportTo(block: EditorBlock, previous: TextFieldValue, actions: BodyEditorActions): TextFieldValue {
    val sentinelAt = text.indexOf(Sentinel)
    val plain = text.replace(Sentinel.toString(), "")
    val shift = { position: Int -> if (sentinelAt in 0 until position) position - 1 else position }
    val start = shift(selection.min).coerceIn(0, plain.length)
    val end = shift(selection.max).coerceIn(0, plain.length)

    if (sentinelAt < 0 && plain == block.text.text) {
        actions.onBackspaceAtStart(block.id)

        return previous.copy(selection = TextRange(1))
    }

    if ('\n' in plain) {
        actions.onTextChange(block.id, plain, start, end)

        // The editor splits the block; this field keeps the first line and the focus moves on.
        return fieldValue(plain.substringBefore('\n'), null)
    }

    if (plain != block.text.text) {
        actions.onTextChange(block.id, plain, start, end)
    } else {
        actions.onSelect(block.id, start, end)
    }

    return TextFieldValue(
        text = "$Sentinel$plain",
        selection = TextRange(start + 1, end + 1),
        composition = composition?.let { TextRange(shift(it.min) + 1, shift(it.max) + 1) },
    )
}

/** Shows the block's styles over the field's plain text, shifted past the sentinel. */
private class SpanTransformation(private val richText: RichText) : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val styled = richText.toAnnotatedString()
        val shown = AnnotatedString.Builder(text.text).apply {
            styled.spanStyles.forEach { range ->
                val start = (range.start + 1).coerceAtMost(text.length)
                val end = (range.end + 1).coerceAtMost(text.length)
                if (start < end) {
                    addStyle(range.item, start, end)
                }
            }
        }.toAnnotatedString()

        return TransformedText(shown, OffsetMapping.Identity)
    }

    override fun equals(other: Any?): Boolean = other is SpanTransformation && other.richText == richText

    override fun hashCode(): Int = richText.hashCode()
}
