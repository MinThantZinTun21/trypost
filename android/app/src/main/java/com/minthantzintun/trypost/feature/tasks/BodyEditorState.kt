package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.BodyBlock
import com.minthantzintun.trypost.core.model.InlineStyle
import com.minthantzintun.trypost.core.model.RichText
import com.minthantzintun.trypost.core.model.StyledRange
import com.minthantzintun.trypost.core.model.TaskBody

/** One line of the body editor, with an id that survives splits and joins around it. */
data class EditorBlock(
    val id: Long,
    val kind: BlockKind,
    val text: RichText,
    val isChecked: Boolean = false,
)

/** The cursor, or a selection, inside one block's plain text. */
data class EditorSelection(val blockId: Long, val start: Int, val end: Int = start) {
    val isCollapsed: Boolean get() = start == end
}

/**
 * The Task body as the editor holds it: a block per line, each a text
 * field. Enter continues a list and ends it on an empty item; Backspace at a
 * block's start turns it to text, then joins it with the line above.
 */
data class BodyEditorState(
    val blocks: List<EditorBlock>,
    val selection: EditorSelection? = null,
    /** Styles turned on or off for what is typed next at the cursor; null follows the text before it. */
    val pendingStyles: Set<InlineStyle>? = null,
    /** Goes up when the cursor must move to [selection], e.g. after a split or a join. */
    val focusRequest: Int = 0,
) {
    private val nextId: Long get() = (blocks.maxOfOrNull { it.id } ?: -1) + 1

    /** True when no block has any text, so there is no body to save. */
    val isBlank: Boolean get() = blocks.all { it.text.text.isBlank() }

    /** The number each block shows: its place in its run of numbered items, 0 for other kinds. */
    val numbers: List<Int>
        get() {
            var number = 0
            return blocks.map { block ->
                number = if (block.kind == BlockKind.Numbered) number + 1 else 0
                number
            }
        }

    /** The body's Markdown, or "" when it is blank. Numbered runs count from 1. */
    fun toMarkdown(): String {
        if (isBlank) {
            return ""
        }

        return TaskBody(
            blocks.zip(numbers) { block, number -> BodyBlock(block.kind, block.text, block.isChecked, number.coerceAtLeast(1)) },
        ).toMarkdown()
    }

    /** The kind of the block the cursor is in. */
    val currentKind: BlockKind? get() = selection?.let { block(it.blockId)?.kind }

    /** The styles the toolbar shows as on: of what is typed next, or of the whole selection. */
    val activeStyles: Set<InlineStyle>
        get() {
            val selection = selection ?: return emptySet()
            val block = block(selection.blockId) ?: return emptySet()
            if (selection.isCollapsed) {
                return pendingStyles ?: block.text.inheritedStyles(selection.start)
            }

            val styles = block.text.charStyles()
            val shown = (selection.start until selection.end.coerceAtMost(block.text.text.length)).filterNot { block.text.text[it].isWhitespace() }
            if (shown.isEmpty()) {
                return emptySet()
            }

            return InlineStyle.entries.filterTo(mutableSetOf()) { style -> shown.all { style in styles[it] } }
        }

    fun select(blockId: Long, start: Int, end: Int = start): BodyEditorState {
        val selection = EditorSelection(blockId, start, end)

        return if (selection == this.selection) this else copy(selection = selection, pendingStyles = null)
    }

    /**
     * The field of [blockId] now reads [text] with the cursor or selection at
     * [start]..[end]. Typed text takes the pending styles, else those of the
     * text before it; a new line splits the block (Enter or a paste).
     */
    fun changeText(blockId: Long, text: String, start: Int, end: Int = start): BodyEditorState {
        val index = indexOf(blockId) ?: return this
        val block = blocks[index]
        val old = block.text.text
        if (text == old) {
            return select(blockId, start, end)
        }

        val prefix = old.commonPrefixWith(text).length
        val suffix = old.substring(prefix).commonSuffixWith(text.substring(prefix)).length
        val removedEnd = old.length - suffix
        val inserted = text.substring(prefix, text.length - suffix)
        val oldStyles = block.text.charStyles()
        val insertedStyles = pendingStyles ?: block.text.inheritedStyles(prefix)
        val styles = oldStyles.subList(0, prefix) + List(inserted.length) { insertedStyles } + oldStyles.subList(removedEnd, old.length)

        if ('\n' in inserted) {
            return split(index, block, text, styles, start)
        }

        val isTypingOn = pendingStyles != null && inserted.isNotEmpty() && removedEnd == prefix

        return copy(
            blocks = blocks.replaced(index, block.copy(text = richTextOf(text, styles))),
            selection = EditorSelection(blockId, start, end),
            pendingStyles = if (isTypingOn) pendingStyles else null,
        )
    }

    /** Backspace with the cursor at the start of [blockId]. */
    fun backspaceAtStart(blockId: Long): BodyEditorState {
        val index = indexOf(blockId) ?: return this
        val block = blocks[index]

        if (block.kind != BlockKind.Paragraph) {
            return copy(
                blocks = blocks.replaced(index, block.copy(kind = BlockKind.Paragraph, isChecked = false)),
                selection = EditorSelection(blockId, 0),
                pendingStyles = null,
                focusRequest = focusRequest + 1,
            )
        }
        if (index == 0) {
            return this
        }

        val above = blocks[index - 1]
        val joined = richTextOf(above.text.text + block.text.text, above.text.charStyles() + block.text.charStyles())

        return copy(
            blocks = blocks.replaced(index - 1, above.copy(text = joined)).filterIndexed { position, _ -> position != index },
            selection = EditorSelection(above.id, above.text.text.length),
            pendingStyles = null,
            focusRequest = focusRequest + 1,
        )
    }

    /** Makes the cursor's block [kind], or plain text when it already is one. */
    fun toggleKind(kind: BlockKind): BodyEditorState {
        val selection = selection ?: return this
        val index = indexOf(selection.blockId) ?: return this
        val block = blocks[index]
        val next = if (block.kind == kind) BlockKind.Paragraph else kind

        return copy(blocks = blocks.replaced(index, block.copy(kind = next, isChecked = block.isChecked && next == BlockKind.Checkbox)))
    }

    /** Turns [style] on or off over the selection, or for what is typed next at the cursor. */
    fun toggleStyle(style: InlineStyle): BodyEditorState {
        val selection = selection ?: return this
        val index = indexOf(selection.blockId) ?: return this
        val block = blocks[index]

        if (selection.isCollapsed) {
            val current = pendingStyles ?: block.text.inheritedStyles(selection.start)
            return copy(pendingStyles = if (style in current) current - style else current + style)
        }

        val isOn = style in activeStyles
        val range = selection.start until selection.end.coerceAtMost(block.text.text.length)
        val styles = block.text.charStyles().mapIndexed { position, set ->
            when {
                position !in range -> set
                isOn -> set - style
                else -> set + style
            }
        }

        return copy(blocks = blocks.replaced(index, block.copy(text = richTextOf(block.text.text, styles))))
    }

    fun toggleChecked(blockId: Long): BodyEditorState {
        val index = indexOf(blockId) ?: return this
        val block = blocks[index]

        return copy(blocks = blocks.replaced(index, block.copy(isChecked = !block.isChecked)))
    }

    private fun block(id: Long): EditorBlock? = blocks.firstOrNull { it.id == id }

    private fun indexOf(id: Long): Int? = blocks.indexOfFirst { it.id == id }.takeIf { it >= 0 }

    /** Splits [block] at each new line of [text]; Enter on an empty list item ends the list instead. */
    private fun split(index: Int, block: EditorBlock, text: String, styles: List<Set<InlineStyle>>, cursor: Int): BodyEditorState {
        if (text == "\n" && block.text.text.isEmpty() && block.kind != BlockKind.Paragraph) {
            return copy(
                blocks = blocks.replaced(index, block.copy(kind = BlockKind.Paragraph, isChecked = false)),
                selection = EditorSelection(block.id, 0),
                pendingStyles = null,
                focusRequest = focusRequest + 1,
            )
        }

        val nextKind = when (block.kind) {
            BlockKind.Bullet, BlockKind.Numbered, BlockKind.Checkbox -> block.kind
            else -> BlockKind.Paragraph
        }
        var id = nextId
        var offset = 0
        var selection = EditorSelection(block.id, 0)
        val lines = text.split('\n').mapIndexed { position, line ->
            val lineStyles = styles.subList(offset, offset + line.length)
            val lineBlock = if (position == 0) {
                block.copy(text = richTextOf(line, lineStyles))
            } else {
                EditorBlock(id = id++, kind = nextKind, text = richTextOf(line, lineStyles))
            }
            if (cursor in offset..offset + line.length) {
                selection = EditorSelection(lineBlock.id, cursor - offset)
            }
            offset += line.length + 1
            lineBlock
        }

        return copy(
            blocks = blocks.subList(0, index) + lines + blocks.subList(index + 1, blocks.size),
            selection = selection,
            pendingStyles = null,
            focusRequest = focusRequest + 1,
        )
    }

    companion object {
        /** The editor over a stored body; an empty one is a single empty line. */
        fun fromMarkdown(markdown: String?): BodyEditorState = BodyEditorState(
            TaskBody.parse(markdown.orEmpty()).blocks.mapIndexed { index, block ->
                EditorBlock(id = index.toLong(), kind = block.kind, text = block.text, isChecked = block.isChecked)
            },
        )

        /** A new checklist: one empty checkbox with the cursor in it. */
        fun checklist(): BodyEditorState = BodyEditorState(
            blocks = listOf(EditorBlock(id = 0, kind = BlockKind.Checkbox, text = RichText(""))),
            selection = EditorSelection(0, 0),
            focusRequest = 1,
        )
    }
}

/** Each character's styles. */
internal fun RichText.charStyles(): List<Set<InlineStyle>> = text.indices.map { stylesAt(it) }

/** The styles of the nearest word before [index], which typing there carries on. */
internal fun RichText.inheritedStyles(index: Int): Set<InlineStyle> {
    val before = (index.coerceAtMost(text.length) - 1 downTo 0).firstOrNull { !text[it].isWhitespace() } ?: return emptySet()

    return stylesAt(before)
}

/** Rich text from [text] and each character's [styles]. */
internal fun richTextOf(text: String, styles: List<Set<InlineStyle>>): RichText {
    val spans = InlineStyle.entries.flatMap { style ->
        val ranges = mutableListOf<StyledRange>()
        var start = -1
        for (index in 0..text.length) {
            val isOn = index < text.length && style in styles[index]
            if (isOn && start < 0) {
                start = index
            } else if (!isOn && start >= 0) {
                ranges += StyledRange(style, start, index)
                start = -1
            }
        }
        ranges
    }

    return RichText(text, spans)
}

private fun <T> List<T>.replaced(index: Int, value: T): List<T> = toMutableList().also { it[index] = value }
