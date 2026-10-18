package com.minthantzintun.trypost.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.settings.ThemeMode
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

/** The Owner's theme for the whole app; `null` until it has been read, so the app never flashes the wrong one. */
@HiltViewModel
class AppThemeViewModel @Inject constructor(
    settingsStore: SettingsStore,
) : ViewModel() {
    val themeMode: StateFlow<ThemeMode?> = settingsStore.themeMode
        .stateIn(viewModelScope, SharingStarted.Eagerly, null)
}
