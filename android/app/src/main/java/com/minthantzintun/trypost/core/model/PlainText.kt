package com.minthantzintun.trypost.core.model

private val BlockBreaks = Regex("(?i)<br\\s*/?>|</p>|</div>|</li>")
private val Tags = Regex("<[^>]+>")
private val BlankLines = Regex("\\n{3,}")
private val Entities = mapOf(
    "&nbsp;" to " ",
    "&lt;" to "<",
    "&gt;" to ">",
    "&quot;" to "\"",
    "&#39;" to "'",
    "&#039;" to "'",
    "&amp;" to "&",
)

/**
 * Post content as plain text. The composer saves plain text, but older Posts
 * may hold HTML; the app shows them without tags.
 */
fun String.toPlainText(): String {
    var text = replace(BlockBreaks, "\n").replace(Tags, "")
    Entities.forEach { (entity, character) -> text = text.replace(entity, character) }

    return text.replace("\r\n", "\n").replace(BlankLines, "\n\n").trim()
}
