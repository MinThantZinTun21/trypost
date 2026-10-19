package com.minthantzintun.trypost.core.ui

import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import com.minthantzintun.trypost.core.model.TaskBody
import org.junit.Assert.assertEquals
import org.junit.Test

class TaskBodyTextTest {
    @Test
    fun `a body line shows its styles without the marks`() {
        val shown = TaskBody.parse("Get **milk**, *eggs* and ~~ham~~").blocks.single().text.toAnnotatedString()

        assertEquals("Get milk, eggs and ham", shown.text)
        assertEquals(
            listOf(Triple(4, 8, FontWeight.Bold), Triple(10, 14, FontStyle.Italic), Triple(19, 22, TextDecoration.LineThrough)),
            shown.spanStyles.map { range ->
                Triple(range.start, range.end, range.item.fontWeight ?: range.item.fontStyle ?: range.item.textDecoration)
            },
        )
    }
}
