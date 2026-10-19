package com.minthantzintun.trypost.feature.settings

import com.minthantzintun.trypost.BuildConfig
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.settings.ThemeMode
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.FakeSettingsStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.serverUrl
import com.minthantzintun.trypost.testing.testApiFactory
import com.minthantzintun.trypost.testing.testJson
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import java.util.concurrent.TimeUnit

class SettingsViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var sessionStore: FakeSessionStore
    private val settingsStore = FakeSettingsStore()

    @Before
    fun setUp() {
        server.start()
        sessionStore = FakeSessionStore(Session(serverUrl = server.serverUrl(), token = "secret", ownerName = "Lucky"))
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): SettingsViewModel = SettingsViewModel(
        settingsStore,
        SessionRepository(sessionStore, testApiFactory(), testJson),
    )

    @Test
    fun `it shows the theme, the server, the owner and the app version`() = runTest {
        val state = viewModel().state.first { it.serverUrl != null }

        assertEquals(ThemeMode.System, state.themeMode)
        assertEquals(server.serverUrl(), state.serverUrl)
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
    fun `signing out revokes this phone's token on the server, then removes the session but keeps the theme`() = runTest {
        server.enqueue(MockResponse.Builder().code(204).build())
        settingsStore.setThemeMode(ThemeMode.Light)
        val viewModel = viewModel()

        viewModel.signOut()

        assertNull(sessionStore.session.first { it == null })
        val request = server.takeRequest(5, TimeUnit.SECONDS)
        assertNotNull("Sign out never called the server", request)
        assertEquals("DELETE", request!!.method)
        assertEquals("/mobile/v1/session", request.url.encodedPath)
        assertEquals("Bearer secret", request.headers["Authorization"])
        assertEquals(ThemeMode.Light, settingsStore.themeMode.value)
    }

    @Test
    fun `signing out still removes the session when the server can't be reached`() = runTest {
        server.close()

        viewModel().signOut()

        assertNull(sessionStore.session.first { it == null })
    }

    @Test
    fun `signing out still removes the session when the token was already revoked`() = runTest {
        server.enqueue(json("""{"message":"Unauthorized."}""", code = 401))

        viewModel().signOut()

        assertNull(sessionStore.session.first { it == null })
    }
}
