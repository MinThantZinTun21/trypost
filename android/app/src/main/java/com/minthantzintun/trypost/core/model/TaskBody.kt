package com.minthantzintun.trypost.core.model

/**
 * A Task's body (ADR 0016, amended): the Markdown subset the server stores
 * as sent in `description`. One block per line, no nesting:
 *
 * - `# ` and `## ` headings, `- ` bullets, `1. ` numbered items,
 *   `- [ ] ` and `- [x] ` checkboxes, and paragraphs;
 * - `**bold**`, `*italic*` and `~~strike~~` inside any block, with `\`
 *   escaping a `\`, `*` or `~`, and a `\` before a paragraph whose text
 *   would read as a heading or list item.
 *
 * Writing picks the plainest Markdown that reads back the same, so text that
 * only looks like Markdown, such as `5*3`, is kept as typed.
 */
data class TaskBody(val blocks: List<BodyBlock>) {

    /** The body as the server stores it. */
    fun toMarkdown(): String = blocks.joinToString("\n") { BlockSyntax.write(it) }

    /** How many checkboxes are ticked, or null when the body has none. */
    fun checklist(): ChecklistProgress? {
        val checkboxes = blocks.filter { it.kind == BlockKind.Checkbox }

        return if (checkboxes.isEmpty()) null else ChecklistProgress(checkboxes.count { it.isChecked }, checkboxes.size)
    }

    companion object {
        fun parse(markdown: String): TaskBody = TaskBody(markdown.split('\n').map(BlockSyntax::read))

        /**
         * [markdown] with its [index]th checkbox (counting from 0) ticked or
         * unticked. Only that line changes, so a tick never rewrites the rest
         * of the body; an index past the last checkbox changes nothing.
         */
        fun toggleCheckbox(markdown: String, index: Int): String {
            var seen = -1
            val lines = markdown.split('\n').map { line ->
                if (BlockSyntax.read(line).kind != BlockKind.Checkbox) {
                    return@map line
                }
                seen++
                if (seen != index) {
                    return@map line
                }
                val mark = if (line[3] == ' ') 'x' else ' '
                line.substring(0, 3) + mark + line.substring(4)
            }

            return lines.joinToString("\n")
        }
    }
}

enum class BlockKind { Paragraph, Heading1, Heading2, Bullet, Numbered, Checkbox }

/** One line of a [TaskBody]. [number] is a Numbered item's own number; [isChecked] a Checkbox's tick. */
data class BodyBlock(
    val kind: BlockKind,
    val text: RichText,
    val isChecked: Boolean = false,
    val number: Int = 1,
)

data class ChecklistProgress(val checked: Int, val total: Int)

enum class InlineStyle { Bold, Italic, Strike }

/** [style] over the characters from [start] up to, not including, [end]. */
data class StyledRange(val style: InlineStyle, val start: Int, val end: Int)

/**
 * Plain [text] with inline styles over it. The spans are kept in one shape
 * (merged, sorted, and never starting or ending on a space), so two texts
 * that look the same are equal.
 */
class RichText(val text: String, spans: List<StyledRange> = emptyList()) {
    val spans: List<StyledRange> = normalize(text, spans)

    /** The styles of the character at [index]. */
    fun stylesAt(index: Int): Set<InlineStyle> = spans.filter { index >= it.start && index < it.end }.mapTo(mutableSetOf()) { it.style }

    override fun equals(other: Any?): Boolean = other is RichText && text == other.text && spans == other.spans

    override fun hashCode(): Int = 31 * text.hashCode() + spans.hashCode()

    override fun toString(): String = "RichText(text=$text, spans=$spans)"

    private companion object {
        /** A space takes a style only when the words on both sides of it have it. */
        fun normalize(text: String, spans: List<StyledRange>): List<StyledRange> {
            val length = text.length

            return InlineStyle.entries.flatMap { style ->
                val marked = BooleanArray(length)
                spans.filter { it.style == style }.forEach { span ->
                    for (index in span.start.coerceAtLeast(0) until span.end.coerceAtMost(length)) {
                        marked[index] = true
                    }
                }
                val styled = BooleanArray(length) { index ->
                    if (!text[index].isWhitespace()) {
                        marked[index]
                    } else {
                        val before = (index - 1 downTo 0).firstOrNull { !text[it].isWhitespace() }
                        val after = (index + 1 until length).firstOrNull { !text[it].isWhitespace() }
                        before != null && after != null && marked[before] && marked[after]
                    }
                }
                runsOf(styled).map { (start, end) -> StyledRange(style, start, end) }
            }.sortedWith(compareBy({ it.start }, { it.style.ordinal }, { it.end }))
        }

        fun runsOf(flags: BooleanArray): List<Pair<Int, Int>> {
            val runs = mutableListOf<Pair<Int, Int>>()
            var start = -1
            for (index in 0..flags.size) {
                val isOn = index < flags.size && flags[index]
                if (isOn && start < 0) {
                    start = index
                } else if (!isOn && start >= 0) {
                    runs += start to index
                    start = -1
                }
            }

            return runs
        }
    }
}

/** Reads and writes one line: its block prefix, then [InlineSyntax]. */
private object BlockSyntax {
    private val heading2 = Regex("^## (.*)$")
    private val heading1 = Regex("^# (.*)$")
    private val checkbox = Regex("^- \\[([ xX])](?: (.*))?$")
    private val bullet = Regex("^-(?: (.*))?$")
    private val numbered = Regex("^(\\d{1,9})\\.(?: (.*))?$")

    /** A paragraph line starting with `\` and one of these is escaped from reading as a heading or list item. */
    private const val BLOCK_ESCAPABLE = "#-0123456789"

    fun read(line: String): BodyBlock {
        if (line.length >= 2 && line[0] == '\\' && line[1] in BLOCK_ESCAPABLE) {
            return BodyBlock(BlockKind.Paragraph, InlineSyntax.read(line.substring(1)))
        }

        heading2.matchEntire(line)?.let { return BodyBlock(BlockKind.Heading2, InlineSyntax.read(it.groupValues[1])) }
        heading1.matchEntire(line)?.let { return BodyBlock(BlockKind.Heading1, InlineSyntax.read(it.groupValues[1])) }
        checkbox.matchEntire(line)?.let {
            return BodyBlock(BlockKind.Checkbox, InlineSyntax.read(it.groupValues[2]), isChecked = it.groupValues[1] != " ")
        }
        bullet.matchEntire(line)?.let { return BodyBlock(BlockKind.Bullet, InlineSyntax.read(it.groupValues[1])) }
        numbered.matchEntire(line)?.let {
            return BodyBlock(BlockKind.Numbered, InlineSyntax.read(it.groupValues[2]), number = it.groupValues[1].toInt())
        }

        return BodyBlock(BlockKind.Paragraph, InlineSyntax.read(line))
    }

    fun write(block: BodyBlock): String {
        val text = InlineSyntax.write(block.text)
        val prefix = when (block.kind) {
            BlockKind.Paragraph -> ""
            BlockKind.Heading1 -> "# "
            BlockKind.Heading2 -> "## "
            BlockKind.Bullet -> "- "
            BlockKind.Numbered -> "${block.number}. "
            BlockKind.Checkbox -> if (block.isChecked) "- [x] " else "- [ ] "
        }
        // An empty list item has no trailing space, which the server would trim off the last line.
        val isBareListItem = text.isEmpty() && block.kind in setOf(BlockKind.Bullet, BlockKind.Numbered, BlockKind.Checkbox)
        val line = if (isBareListItem) prefix.trimEnd() else prefix + text

        if (block.kind != BlockKind.Paragraph || read(line) == block || !startsLikeBlock(line)) {
            return line
        }

        return "\\" + line
    }

    private fun startsLikeBlock(line: String): Boolean {
        val first = line.firstOrNull() ?: return false

        return first in BLOCK_ESCAPABLE || first == '\\' && line.length > 1 && line[1] in BLOCK_ESCAPABLE
    }
}

/** Reads and writes `**bold**`, `*italic*`, `~~strike~~` and `\` escapes. */
private object InlineSyntax {
    private const val ESCAPABLE = "\\*~"

    fun read(source: String): RichText {
        val text = StringBuilder()
        val spans = mutableListOf<StyledRange>()
        readInto(source, 0, source.length, text, spans)

        return RichText(text.toString(), spans)
    }

    /** The plainest Markdown that reads back as [richText]; literal marks are escaped only when they would be read as styles. */
    fun write(richText: RichText): String {
        val plain = write(richText, escapeAll = false)
        if (read(plain) == richText) {
            return plain
        }

        return write(richText, escapeAll = true)
    }

    private fun readInto(source: String, from: Int, to: Int, text: StringBuilder, spans: MutableList<StyledRange>) {
        var index = from
        while (index < to) {
            val char = source[index]
            if (char == '\\' && index + 1 < to && source[index + 1] in ESCAPABLE) {
                text.append(source[index + 1])
                index += 2
                continue
            }
            val match = if (char == '*' || char == '~') opening(source, index, to) else null
            if (match != null) {
                val start = text.length
                readInto(source, index + match.markLength, match.closeAt, text, spans)
                spans += StyledRange(match.style, start, text.length)
                index = match.closeAt + match.markLength
                continue
            }
            text.append(char)
            index++
        }
    }

    private class Opening(val style: InlineStyle, val markLength: Int, val closeAt: Int)

    /** The style opened by the mark at [at], with where it closes, or null when the mark is a literal character. */
    private fun opening(source: String, at: Int, to: Int): Opening? {
        val char = source[at]
        val runLength = runEnd(source, at, to) - at
        val candidates = when {
            char == '~' && runLength >= 2 -> listOf(InlineStyle.Strike)
            char == '~' -> emptyList()
            runLength >= 2 -> listOf(InlineStyle.Bold, InlineStyle.Italic)
            else -> listOf(InlineStyle.Italic)
        }

        for (style in candidates) {
            val markLength = if (style == InlineStyle.Italic) 1 else 2
            val contentStart = at + markLength
            if (contentStart >= to || source[contentStart].isWhitespace()) {
                continue
            }
            val closeAt = closing(source, char, style, contentStart, to)
            if (closeAt != null) {
                return Opening(style, markLength, closeAt)
            }
        }

        return null
    }

    /**
     * Where [style] opened before [contentStart] closes: the first run of
     * [char] after a non-space. A run followed by more text closes on its
     * first marks (`**a***b*`), a run at a word's end on its last (`***a***`).
     */
    private fun closing(source: String, char: Char, style: InlineStyle, contentStart: Int, to: Int): Int? {
        val markLength = if (style == InlineStyle.Italic) 1 else 2
        var index = contentStart
        while (index < to) {
            if (source[index] != char || isEscaped(source, index)) {
                index++
                continue
            }
            val end = runEnd(source, index, to)
            val length = end - index
            val followsWord = index > contentStart && !source[index - 1].isWhitespace()
            val fits = when (style) {
                InlineStyle.Italic -> length % 2 == 1
                else -> length >= 2
            }
            if (followsWord && fits) {
                val isBeforeWord = end < to && !source[end].isWhitespace()

                return if (isBeforeWord) index else end - markLength
            }
            index = end
        }

        return null
    }

    private fun runEnd(source: String, from: Int, to: Int): Int {
        var end = from
        while (end < to && source[end] == source[from]) {
            end++
        }

        return end
    }

    private fun isEscaped(source: String, index: Int): Boolean {
        var backslashes = 0
        var before = index - 1
        while (before >= 0 && source[before] == '\\') {
            backslashes++
            before--
        }

        return backslashes % 2 == 1
    }

    private fun write(richText: RichText, escapeAll: Boolean): String {
        val text = richText.text
        val styles = List(text.length) { richText.stylesAt(it) }
        val open = mutableListOf<InlineStyle>()
        val out = StringBuilder()

        for (index in 0..text.length) {
            val wanted = if (index < text.length) styles[index] else emptySet()

            // Close down to the first open style that ends here; the ones above it reopen below.
            val firstEnded = open.indexOfFirst { it !in wanted }
            if (firstEnded >= 0) {
                while (open.size > firstEnded) {
                    out.append(mark(open.removeAt(open.lastIndex)))
                }
            }
            if (index == text.length) {
                break
            }

            val char = text[index]
            if (!char.isWhitespace()) {
                wanted.filter { it !in open }
                    .sortedWith(compareByDescending<InlineStyle> { style -> runEndOf(styles, index, style) }.thenBy { it.ordinal })
                    .forEach { style ->
                        out.append(mark(style))
                        open += style
                    }
            }
            if (char in ESCAPABLE && (escapeAll || char == '\\' && index + 1 < text.length && text[index + 1] in ESCAPABLE)) {
                out.append('\\')
            }
            out.append(char)
        }

        return out.toString()
    }

    private fun runEndOf(styles: List<Set<InlineStyle>>, from: Int, style: InlineStyle): Int {
        var end = from
        while (end < styles.size && style in styles[end]) {
            end++
        }

        return end
    }

    private fun mark(style: InlineStyle): String = when (style) {
        InlineStyle.Bold -> "**"
        InlineStyle.Italic -> "*"
        InlineStyle.Strike -> "~~"
    }
}
