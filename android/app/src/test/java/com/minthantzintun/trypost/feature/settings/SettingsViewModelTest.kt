package com.minthantzintun.trypost.feature.settings

import com.minthantzintun.trypost.BuildConfig
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.settings.ThemeMode
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.FakeSettingsStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.testApiFactory
import com.minthantzintun.trypost.testing.testJson
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test

class SettingsViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val sessionStore = FakeSessionStore(Session(serverUrl = "https://trypost.example.com", token = "secret", ownerName = "Lucky"))
    private val settingsStore = FakeSettingsStore()

    private fun viewModel(): SettingsViewModel = SettingsViewModel(
        settingsStore,
        SessionRepository(sessionStore, testApiFactory(), testJson),
    )

    @Test
    fun `it shows the theme, the server, the owner and the app version`() = runTest {
        val state = viewModel().state.first { it.serverUrl != null }

        assertEquals(ThemeMode.System, state.themeMode)
        assertEquals("https://trypost.example.com", state.serverUrl)
        assertEquals("Lucky", state.ownerName)
        assertEquals(BuildConfig.VERSION_NAME, state.appVersion)
    }

    @Test
    fun `choosing a theme keeps it`() = runTest {
        val viewModel = viewModel()

        viewModel.selectThemeMode(ThemeMode.Dark)

        assertEquals(ThemeMode.Dark, settingsStore.themeMode.value)
        assertEquals(ThemeMode.Dark, viewModel.state.first { it.themeMode == ThemeMode.Dark }.themeMode)
    }

    @Test
    fun `signing out removes the session but keeps the theme`() = runTest {
        settingsStore.setThemeMode(ThemeMode.Light)
        val viewModel = viewModel()

        viewModel.signOut()

        assertNull(sessionStore.session.first { it == null })
        assertEquals(ThemeMode.Light, settingsStore.themeMode.value)
    }
}
