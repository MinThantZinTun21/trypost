package com.minthantzintun.trypost.ui

import com.minthantzintun.trypost.core.settings.ThemeMode
import com.minthantzintun.trypost.testing.FakeSettingsStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

class AppThemeViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun `the stored theme reaches the app and follows changes`() = runTest {
        val store = FakeSettingsStore(ThemeMode.Dark)
        val viewModel = AppThemeViewModel(store)

        assertEquals(ThemeMode.Dark, viewModel.themeMode.filterNotNull().first())

        store.setThemeMode(ThemeMode.Light)

        assertEquals(ThemeMode.Light, viewModel.themeMode.first { it == ThemeMode.Light })
    }

    @Test
    fun `system follows the phone, dark and light force it`() {
        assertTrue(ThemeMode.System.isDark(systemIsDark = true))
        assertFalse(ThemeMode.System.isDark(systemIsDark = false))
        assertTrue(ThemeMode.Dark.isDark(systemIsDark = false))
        assertFalse(ThemeMode.Light.isDark(systemIsDark = true))
    }
}
