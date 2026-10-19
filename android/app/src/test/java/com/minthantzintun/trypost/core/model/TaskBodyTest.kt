package com.minthantzintun.trypost.core.model

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import kotlin.random.Random

class TaskBodyTest {
    private fun roundTrip(markdown: String) = assertEquals(markdown, TaskBody.parse(markdown).toMarkdown())

    private fun plain(text: String) = RichText(text)

    @Test
    fun `every block type is read from its line`() {
        val body = TaskBody.parse("# Trip\n## Pack\n- Snacks\n3. Taxi\n- [ ] Charger\n- [x] Passport\nJust text")

        assertEquals(
            listOf(
                BodyBlock(BlockKind.Heading1, plain("Trip")),
                BodyBlock(BlockKind.Heading2, plain("Pack")),
                BodyBlock(BlockKind.Bullet, plain("Snacks")),
                BodyBlock(BlockKind.Numbered, plain("Taxi"), number = 3),
                BodyBlock(BlockKind.Checkbox, plain("Charger")),
                BodyBlock(BlockKind.Checkbox, plain("Passport"), isChecked = true),
                BodyBlock(BlockKind.Paragraph, plain("Just text")),
            ),
            body.blocks,
        )
    }

    @Test
    fun `every block type and blank line survives a round trip`() {
        roundTrip("# Trip\n\n## Pack\n- Snacks\n1. Book\n2. Pay\n- [ ] Charger\n- [x] Passport\nJust text\n")
    }

    @Test
    fun `inline styles are read as spans over the plain text`() {
        val text = TaskBody.parse("Get **milk**, *eggs* and ~~ham~~").blocks.single().text

        assertEquals("Get milk, eggs and ham", text.text)
        assertEquals(
            listOf(
                StyledRange(InlineStyle.Bold, 4, 8),
                StyledRange(InlineStyle.Italic, 10, 14),
                StyledRange(InlineStyle.Strike, 19, 22),
            ),
            text.spans,
        )
    }

    @Test
    fun `nested and mixed inline styles survive a round trip`() {
        roundTrip("***both*** and **bold *italic* bold** and ~~gone **bold**~~")
        roundTrip("- [ ] **Call** the *supplier*")
        roundTrip("## A ~~struck~~ heading")
    }

    @Test
    fun `nested marks are read inside one another`() {
        val text = TaskBody.parse("***both***").blocks.single().text

        assertEquals("both", text.text)
        assertEquals(listOf(StyledRange(InlineStyle.Bold, 0, 4), StyledRange(InlineStyle.Italic, 0, 4)), text.spans)
    }

    @Test
    fun `text that only looks like markdown is kept as it is`() {
        roundTrip("5*3 = 15")
        roundTrip("#")
        roundTrip("#hashtag")
        roundTrip("a * b * c")
        roundTrip("~ approx ~")
        roundTrip("C:\\temp\\file")
        roundTrip("-dash")
        roundTrip("1.5 litres")

        assertEquals(plain("5*3 = 15"), TaskBody.parse("5*3 = 15").blocks.single().text)
    }

    @Test
    fun `escaped marks are literal characters`() {
        val text = TaskBody.parse("Book \\*the\\* taxi \\~~now\\~~ \\\\").blocks.single().text

        assertEquals(plain("Book *the* taxi ~~now~~ \\"), text)
    }

    @Test
    fun `a paragraph that would read as a list or heading is escaped and read back`() {
        val blocks = listOf(
            BodyBlock(BlockKind.Paragraph, plain("- not a bullet")),
            BodyBlock(BlockKind.Paragraph, plain("# not a heading")),
            BodyBlock(BlockKind.Paragraph, plain("2. not a number")),
            BodyBlock(BlockKind.Paragraph, plain("\\- a backslash")),
        )

        val markdown = TaskBody(blocks).toMarkdown()

        assertEquals("\\- not a bullet\n\\# not a heading\n\\2. not a number\n\\\\- a backslash", markdown)
        assertEquals(blocks, TaskBody.parse(markdown).blocks)
    }

    @Test
    fun `literal marks that would read as styles are escaped`() {
        val block = BodyBlock(BlockKind.Paragraph, plain("**not bold** and *not italic*"))

        val markdown = TaskBody(listOf(block)).toMarkdown()

        assertEquals(block, TaskBody.parse(markdown).blocks.single())
    }

    @Test
    fun `styled text written by the editor reads back the same`() {
        val block = BodyBlock(
            BlockKind.Checkbox,
            RichText(
                "buy 5*3 eggs now",
                listOf(StyledRange(InlineStyle.Bold, 0, 3), StyledRange(InlineStyle.Italic, 2, 12), StyledRange(InlineStyle.Strike, 13, 16)),
            ),
        )

        assertEquals(block, TaskBody.parse(TaskBody(listOf(block)).toMarkdown()).blocks.single())
    }

    @Test
    fun `a style over spaces at its ends is trimmed to the words`() {
        val block = BodyBlock(BlockKind.Paragraph, RichText("say hi there", listOf(StyledRange(InlineStyle.Bold, 3, 7))))

        val markdown = TaskBody(listOf(block)).toMarkdown()

        assertEquals("say **hi** there", markdown)
    }

    @Test
    fun `empty list items are written without a trailing space`() {
        val markdown = TaskBody(
            listOf(
                BodyBlock(BlockKind.Checkbox, plain("")),
                BodyBlock(BlockKind.Checkbox, plain(""), isChecked = true),
                BodyBlock(BlockKind.Bullet, plain("")),
                BodyBlock(BlockKind.Numbered, plain(""), number = 1),
            ),
        ).toMarkdown()

        assertEquals("- [ ]\n- [x]\n-\n1.", markdown)
        assertEquals(listOf(BlockKind.Checkbox, BlockKind.Checkbox, BlockKind.Bullet, BlockKind.Numbered), TaskBody.parse(markdown).blocks.map { it.kind })
    }

    @Test
    fun `checklist progress counts the checkboxes`() {
        val body = TaskBody.parse("Shopping\n- [x] Milk\n- [ ] Eggs\n- [x] Bread\n- Note")

        assertEquals(ChecklistProgress(checked = 2, total = 3), body.checklist())
        assertNull(TaskBody.parse("No list here").checklist())
    }

    @Test
    fun `toggling a checkbox changes only its own line`() {
        val markdown = "Shopping  \n- [ ] Milk\n- [x] *Eggs*\n- [ ] Bread\n5*3"

        assertEquals("Shopping  \n- [ ] Milk\n- [ ] *Eggs*\n- [ ] Bread\n5*3", TaskBody.toggleCheckbox(markdown, 1))
        assertEquals("Shopping  \n- [ ] Milk\n- [x] *Eggs*\n- [x] Bread\n5*3", TaskBody.toggleCheckbox(markdown, 2))
    }

    @Test
    fun `toggling reads a capital X as checked and leaves a missing checkbox alone`() {
        assertEquals("- [ ] Done", TaskBody.toggleCheckbox("- [X] Done", 0))
        assertEquals("- [ ] Only one", TaskBody.toggleCheckbox("- [ ] Only one", 5))
    }

    @Test
    fun `an escaped dash line is not a checkbox to toggle`() {
        assertEquals("\\- [ ] text\n- [x] real", TaskBody.toggleCheckbox("\\- [ ] text\n- [ ] real", 0))
    }

    @Test
    fun `any text reads back the same after it is written`() {
        val random = Random(16)
        val alphabet = "ab  *~\\#-1.[x]\n"

        repeat(5000) {
            val markdown = String(CharArray(random.nextInt(24)) { alphabet[random.nextInt(alphabet.length)] })
            val body = TaskBody.parse(markdown)

            assertEquals("for \"$markdown\"", body, TaskBody.parse(body.toMarkdown()))
        }
    }

    @Test
    fun `an empty body has one empty paragraph`() {
        assertEquals(listOf(BodyBlock(BlockKind.Paragraph, plain(""))), TaskBody.parse("").blocks)
        roundTrip("")
    }
}
