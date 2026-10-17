package com.minthantzintun.trypost.feature.signin

import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.serverUrl
import com.minthantzintun.trypost.testing.testApiFactory
import com.minthantzintun.trypost.testing.testJson
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class SignInViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private val store = FakeSessionStore()
    private lateinit var viewModel: SignInViewModel

    @Before
    fun setUp() {
        server.start()
        viewModel = SignInViewModel(SessionRepository(store, testApiFactory(), testJson))
    }

    @After
    fun tearDown() {
        server.close()
    }

    private suspend fun submit(serverUrl: String = server.serverUrl(), token: String = "good-token"): SignInUiState {
        viewModel.onServerUrlChange(serverUrl)
        viewModel.onTokenChange(token)
        viewModel.submit()

        return viewModel.state.first { !it.isSubmitting }
    }

    @Test
    fun `the server address is prefilled with production`() {
        assertEquals("https://trypost21.duckdns.org", viewModel.state.value.serverUrl)
    }

    @Test
    fun `a token the server accepts is kept with the owner's name`() = runTest {
        server.enqueue(json("""{"name":"The Owner"}"""))

        val state = submit(serverUrl = "${server.serverUrl()}/", token = "  good-token  ")

        assertNull(state.error)
        val request = server.takeRequest()
        assertEquals("/mobile/v1/me", request.url.encodedPath)
        assertEquals("Bearer good-token", request.headers["Authorization"])
        assertEquals("application/json", request.headers["Accept"])
        val session = store.current()!!
        assertEquals(server.serverUrl(), session.serverUrl)
        assertEquals("good-token", session.token)
        assertEquals("The Owner", session.ownerName)
    }

    @Test
    fun `a wrong token is reported and not kept`() = runTest {
        server.enqueue(json("""{"message":"Unauthorized."}""", code = 401))

        val state = submit(token = "wrong")

        assertEquals(SignInError.Api(ApiError.Unauthorized), state.error)
        assertNull(store.current())
    }

    @Test
    fun `a server without the mobile api is reported`() = runTest {
        server.enqueue(json("""{"message":"Not Found"}""", code = 404))

        assertEquals(SignInError.Api(ApiError.NotFound), submit().error)
        assertNull(store.current())
    }

    @Test
    fun `an unreachable server is reported`() = runTest {
        val url = server.serverUrl()
        server.close()

        assertEquals(SignInError.Api(ApiError.Unreachable), submit(serverUrl = url).error)
        assertNull(store.current())
    }

    @Test
    fun `an address that is not a url is refused before calling the server`() = runTest {
        assertEquals(SignInError.InvalidAddress, submit(serverUrl = "not a url").error)
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `an empty token is refused before calling the server`() = runTest {
        assertEquals(SignInError.MissingToken, submit(token = "   ").error)
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `editing a field clears the error`() = runTest {
        submit(token = "")

        viewModel.onTokenChange("x")

        assertNull(viewModel.state.value.error)
    }
}
