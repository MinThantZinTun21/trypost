package com.minthantzintun.trypost.core.ui

import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import com.minthantzintun.trypost.core.model.InlineStyle
import com.minthantzintun.trypost.core.model.RichText

/** The look of one inline style in a Task body. */
fun InlineStyle.spanStyle(): SpanStyle = when (this) {
    InlineStyle.Bold -> SpanStyle(fontWeight = FontWeight.Bold)
    InlineStyle.Italic -> SpanStyle(fontStyle = FontStyle.Italic)
    InlineStyle.Strike -> SpanStyle(textDecoration = TextDecoration.LineThrough)
}

/** The text with its bold, italic and strikethrough shown, the Markdown marks hidden. */
fun RichText.toAnnotatedString(): AnnotatedString = buildAnnotatedString {
    append(text)
    spans.forEach { span -> addStyle(span.style.spanStyle(), span.start, span.end) }
}
