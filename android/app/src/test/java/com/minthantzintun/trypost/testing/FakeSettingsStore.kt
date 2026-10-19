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

    override val pomodoroFocusMinutes = MutableStateFlow(SettingsStore.DEFAULT_FOCUS_MINUTES)

    override suspend fun setPomodoroFocusMinutes(minutes: Int) {
        pomodoroFocusMinutes.value = minutes
    }

    override val pomodoroBreakMinutes = MutableStateFlow(SettingsStore.DEFAULT_BREAK_MINUTES)

    override suspend fun setPomodoroBreakMinutes(minutes: Int) {
        pomodoroBreakMinutes.value = minutes
    }
}
