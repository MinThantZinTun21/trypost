package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.settings.TaskLayout
import com.minthantzintun.trypost.core.settings.ThemeMode
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

class FakeSettingsStore(initial: ThemeMode = ThemeMode.System, initialTaskLayout: TaskLayout = TaskLayout.Grid) : SettingsStore {
    private val state = MutableStateFlow(initial)
    private val layout = MutableStateFlow(initialTaskLayout)

    override val themeMode: StateFlow<ThemeMode> = state

    override suspend fun setThemeMode(mode: ThemeMode) {
        state.value = mode
    }

    override val taskLayout: StateFlow<TaskLayout> = layout

    override suspend fun setTaskLayout(layout: TaskLayout) {
        this.layout.value = layout
    }
}
