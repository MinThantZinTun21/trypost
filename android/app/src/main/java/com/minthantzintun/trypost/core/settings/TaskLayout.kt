package com.minthantzintun.trypost.core.settings

/** How the Tasks list lays out its cards: a two-column grid, or one column. */
enum class TaskLayout(val wire: String) {
    Grid("grid"),
    List("list"),
    ;

    companion object {
        fun fromWire(value: String?): TaskLayout = entries.firstOrNull { it.wire == value } ?: Grid
    }
}
