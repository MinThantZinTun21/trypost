package com.minthantzintun.trypost.feature.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.BuildConfig
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.settings.ThemeMode
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import javax.inject.Inject

data class SettingsUiState(
    val themeMode: ThemeMode = ThemeMode.System,
    val serverUrl: String? = null,
    val ownerName: String? = null,
    val appVersion: String = BuildConfig.VERSION_NAME,
    val pomodoroFocusMinutes: Int = SettingsStore.DEFAULT_FOCUS_MINUTES,
    val pomodoroBreakMinutes: Int = SettingsStore.DEFAULT_BREAK_MINUTES,
)

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val settingsStore: SettingsStore,
    private val sessionRepository: SessionRepository,
) : ViewModel() {

    val state: StateFlow<SettingsUiState> = combine(
        settingsStore.themeMode,
        sessionRepository.session,
        settingsStore.pomodoroFocusMinutes,
        settingsStore.pomodoroBreakMinutes,
    ) { themeMode, session, focusMinutes, breakMinutes ->
        SettingsUiState(
            themeMode = themeMode,
            serverUrl = session?.serverUrl,
            ownerName = session?.ownerName,
            pomodoroFocusMinutes = focusMinutes,
            pomodoroBreakMinutes = breakMinutes,
        )
    }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), SettingsUiState())

    fun selectThemeMode(mode: ThemeMode) {
        viewModelScope.launch { settingsStore.setThemeMode(mode) }
    }

    fun selectPomodoroFocusMinutes(minutes: Int) {
        viewModelScope.launch { settingsStore.setPomodoroFocusMinutes(minutes) }
    }

    fun selectPomodoroBreakMinutes(minutes: Int) {
        viewModelScope.launch { settingsStore.setPomodoroBreakMinutes(minutes) }
    }

    fun signOut() {
        viewModelScope.launch { sessionRepository.signOut() }
    }
}
