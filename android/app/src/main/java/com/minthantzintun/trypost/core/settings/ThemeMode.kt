package com.minthantzintun.trypost.core.settings

/** The Owner's theme: the phone's own setting, or always dark or light. */
enum class ThemeMode(val wire: String) {
    System("system"),
    Dark("dark"),
    Light("light"),
    ;

    fun isDark(systemIsDark: Boolean): Boolean = when (this) {
        System -> systemIsDark
        Dark -> true
        Light -> false
    }

    companion object {
        fun fromWire(value: String?): ThemeMode = entries.firstOrNull { it.wire == value } ?: System
    }
}
