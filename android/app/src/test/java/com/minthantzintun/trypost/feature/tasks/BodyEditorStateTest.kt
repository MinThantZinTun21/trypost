package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.InlineStyle
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class BodyEditorStateTest {
    private fun editor(markdown: String) = BodyEditorState.fromMarkdown(markdown)

    private fun BodyEditorState.idAt(index: Int) = blocks[index].id

    /** Types [typed] at the end of block [index], as the field would report it. */
    private fun BodyEditorState.typeAtEnd(index: Int, typed: String): BodyEditorState {
        val text = blocks[index].text.text + typed

        return changeText(idAt(index), text, text.length)
    }

    @Test
    fun `a stored body opens with its formatting and writes back the same`() {
        val markdown = "# Trip\n- [ ] **Charger**\n- [x] Passport\n1. Book\n2. Pay\n- Snacks\nJust *text*"

        val state = editor(markdown)

        assertEquals(
            listOf(BlockKind.Heading1, BlockKind.Checkbox, BlockKind.Checkbox, BlockKind.Numbered, BlockKind.Numbered, BlockKind.Bullet, BlockKind.Paragraph),
            state.blocks.map { it.kind },
        )
        assertEquals("Charger", state.blocks[1].text.text)
        assertEquals(markdown, state.toMarkdown())
    }

    @Test
    fun `Enter in a checklist item starts the next one, unticked`() {
        val state = editor("- [x] Milk").typeAtEnd(0, "\n")

        assertEquals(listOf(BlockKind.Checkbox, BlockKind.Checkbox), state.blocks.map { it.kind })
        assertEquals(listOf(true, false), state.blocks.map { it.isChecked })
        assertEquals(EditorSelection(state.idAt(1), 0), state.selection)
        assertEquals("- [x] Milk\n- [ ]", state.toMarkdown())
    }

    @Test
    fun `Enter in the middle splits the text and keeps its styles`() {
        val state = editor("- **Bold** tail")
        val id = state.idAt(0)

        val split = state.changeText(id, "Bold\n tail", 5)

        assertEquals(listOf("Bold", " tail"), split.blocks.map { it.text.text })
        assertEquals(listOf(BlockKind.Bullet, BlockKind.Bullet), split.blocks.map { it.kind })
        assertEquals("- **Bold**\n-  tail", split.toMarkdown())
        assertEquals(EditorSelection(split.idAt(1), 0), split.selection)
    }

    @Test
    fun `Enter after a heading or text starts plain text`() {
        val heading = editor("# Title").typeAtEnd(0, "\n")
        val text = editor("Note").typeAtEnd(0, "\n")

        assertEquals(listOf(BlockKind.Heading1, BlockKind.Paragraph), heading.blocks.map { it.kind })
        assertEquals(listOf(BlockKind.Paragraph, BlockKind.Paragraph), text.blocks.map { it.kind })
    }

    @Test
    fun `Enter on an empty list item ends the list`() {
        val state = editor("- [ ] Milk\n- [ ]")

        val ended = state.changeText(state.idAt(1), "\n", 1)

        assertEquals(listOf(BlockKind.Checkbox, BlockKind.Paragraph), ended.blocks.map { it.kind })
        assertEquals(2, ended.blocks.size)
        assertEquals(EditorSelection(state.idAt(1), 0), ended.selection)
    }

    @Test
    fun `Backspace at a list item's start turns it to text, then joins it with the line above`() {
        val state = editor("Shop\n- [x] **Milk**")
        val id = state.idAt(1)

        val asText = state.backspaceAtStart(id)
        assertEquals(listOf(BlockKind.Paragraph, BlockKind.Paragraph), asText.blocks.map { it.kind })
        assertFalse(asText.blocks[1].isChecked)

        val joined = asText.backspaceAtStart(id)
        assertEquals(1, joined.blocks.size)
        assertEquals("Shop**Milk**", joined.toMarkdown())
        assertEquals(EditorSelection(state.idAt(0), 4), joined.selection)
        assertTrue(joined.focusRequest > asText.focusRequest)
    }

    @Test
    fun `Backspace at the very start of the body does nothing`() {
        val state = editor("First")

        assertEquals(state, state.backspaceAtStart(state.idAt(0)))
    }

    @Test
    fun `a paste with new lines makes a block for each line`() {
        val state = editor("- a")

        val pasted = state.changeText(state.idAt(0), "a\nb\nc", 5)

        assertEquals("- a\n- b\n- c", pasted.toMarkdown())
        assertEquals(EditorSelection(pasted.idAt(2), 1), pasted.selection)
    }

    @Test
    fun `a style toggled at the cursor applies to what is typed next`() {
        val state = editor("Buy ").select(0, 4)

        val bold = state.toggleStyle(InlineStyle.Bold)
        assertEquals(setOf(InlineStyle.Bold), bold.activeStyles)

        val typed = bold.changeText(0, "Buy m", 5).changeText(0, "Buy mi", 6).changeText(0, "Buy milk", 8)

        assertEquals("Buy **milk**", typed.toMarkdown())
    }

    @Test
    fun `moving the cursor drops a pending style`() {
        val state = editor("Buy milk").select(0, 3).toggleStyle(InlineStyle.Italic)

        val moved = state.select(0, 8)

        assertEquals(null, moved.pendingStyles)
        assertEquals(emptySet<InlineStyle>(), moved.activeStyles)
    }

    @Test
    fun `typing after a styled word carries its style on`() {
        val state = editor("**Bold**").select(0, 4)

        val typed = state.changeText(0, "Bold x", 6)

        assertEquals("**Bold x**", typed.toMarkdown())
    }

    @Test
    fun `a style over a selection is added, then removed when all of it has it`() {
        val state = editor("say hi there").select(0, 4, 6)

        val bold = state.toggleStyle(InlineStyle.Bold)
        assertEquals("say **hi** there", bold.toMarkdown())
        assertEquals(setOf(InlineStyle.Bold), bold.activeStyles)

        val wider = bold.select(0, 0, 6).toggleStyle(InlineStyle.Bold)
        assertEquals("**say hi** there", wider.toMarkdown())

        val removed = wider.select(0, 0, 6).toggleStyle(InlineStyle.Bold)
        assertEquals("say hi there", removed.toMarkdown())
    }

    @Test
    fun `every inline style is written as its Markdown`() {
        val state = editor("one two three")

        val styled = state
            .select(0, 0, 3).toggleStyle(InlineStyle.Bold)
            .select(0, 4, 7).toggleStyle(InlineStyle.Italic)
            .select(0, 8, 13).toggleStyle(InlineStyle.Strike)

        assertEquals("**one** *two* ~~three~~", styled.toMarkdown())
    }

    @Test
    fun `the block kinds toggle on the cursor's line and back to text`() {
        val state = editor("Line").select(0, 2)

        val written = BlockKind.entries.associateWith { kind -> state.toggleKind(kind).toMarkdown() }

        assertEquals(
            mapOf(
                BlockKind.Paragraph to "Line",
                BlockKind.Heading1 to "# Line",
                BlockKind.Heading2 to "## Line",
                BlockKind.Bullet to "- Line",
                BlockKind.Numbered to "1. Line",
                BlockKind.Checkbox to "- [ ] Line",
            ),
            written,
        )
        assertEquals("Line", state.toggleKind(BlockKind.Bullet).toggleKind(BlockKind.Bullet).toMarkdown())
    }

    @Test
    fun `numbered runs count from one in each run`() {
        val state = editor("5. a\n9. b\nText\n3. c")

        assertEquals(listOf(1, 2, 0, 1), state.numbers)
        assertEquals("1. a\n2. b\nText\n1. c", state.toMarkdown())
    }

    @Test
    fun `ticking in the editor flips only that item`() {
        val state = editor("- [ ] a\n- [ ] b")

        assertEquals("- [ ] a\n- [x] b", state.toggleChecked(state.idAt(1)).toMarkdown())
    }

    @Test
    fun `a body with no text is blank and saves as nothing`() {
        assertTrue(BodyEditorState.checklist().isBlank)
        assertEquals("", BodyEditorState.checklist().toMarkdown())
        assertTrue(editor("\n- [ ]\n  ").isBlank)
        assertFalse(editor("x").isBlank)
    }
}
