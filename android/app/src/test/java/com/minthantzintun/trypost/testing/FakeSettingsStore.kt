package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.settings.ThemeMode
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

class FakeSettingsStore(initial: ThemeMode = ThemeMode.System) : SettingsStore {
    private val state = MutableStateFlow(initial)

    override val themeMode: StateFlow<ThemeMode> = state

    override suspend fun setThemeMode(mode: ThemeMode) {
        state.value = mode
    }
}
