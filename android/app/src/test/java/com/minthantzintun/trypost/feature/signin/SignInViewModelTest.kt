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
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
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

    private suspend fun submit(
        serverUrl: String = server.serverUrl(),
        email: String = "owner@example.com",
        password: String = "right password",
    ): SignInUiState {
        viewModel.onServerUrlChange(serverUrl)
        viewModel.onEmailChange(email)
        viewModel.onPasswordChange(password)
        viewModel.submit()

        return viewModel.state.first { !it.isSubmitting }
    }

    @Test
    fun `the server address is prefilled with production`() {
        assertEquals("https://trypost21.duckdns.org", viewModel.state.value.serverUrl)
    }

    @Test
    fun `the web email and password get a mobile token that is kept with the owner's name`() = runTest {
        server.enqueue(json("""{"token":"phone-token","name":"The Owner"}""", code = 201))

        val state = submit(serverUrl = "${server.serverUrl()}/", email = "  owner@example.com  ", password = " right password ")

        assertNull(state.error)
        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/sessions", request.url.encodedPath)
        assertNull(request.headers["Authorization"])
        assertEquals("application/json", request.headers["Accept"])
        assertEquals(
            buildJsonObject {
                put("email", "owner@example.com")
                put("password", " right password ")
            },
            testJson.parseToJsonElement(request.body!!.utf8()),
        )
        val session = store.current()!!
        assertEquals(server.serverUrl(), session.serverUrl)
        assertEquals("phone-token", session.token)
        assertEquals("The Owner", session.ownerName)
    }

    @Test
    fun `wrong credentials show the server's message and nothing is kept`() = runTest {
        server.enqueue(
            json(
                """{"message":"These credentials do not match our records.","errors":{"email":["These credentials do not match our records."]}}""",
                code = 422,
            ),
        )

        val error = submit(password = "wrong").error

        assertEquals(
            SignInError.Api(
                ApiError.Validation(
                    "These credentials do not match our records.",
                    mapOf("email" to listOf("These credentials do not match our records.")),
                ),
            ),
            error,
        )
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
    fun `an empty email is refused before calling the server`() = runTest {
        assertEquals(SignInError.MissingEmail, submit(email = "   ").error)
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `an empty password is refused before calling the server`() = runTest {
        assertEquals(SignInError.MissingPassword, submit(password = "").error)
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `each error shows under the field it is about`() {
        val credentials = ApiError.Validation("Wrong.", mapOf("email" to listOf("Wrong.")))
        val passwordOnly = ApiError.Validation("Short.", mapOf("password" to listOf("Short.")))

        assertEquals(SignInField.ServerUrl, SignInError.InvalidAddress.field)
        assertEquals(SignInField.ServerUrl, SignInError.Api(ApiError.NotFound).field)
        assertEquals(SignInField.Email, SignInError.MissingEmail.field)
        assertEquals(SignInField.Email, SignInError.Api(credentials).field)
        assertEquals(SignInField.Password, SignInError.MissingPassword.field)
        assertEquals(SignInField.Password, SignInError.Api(passwordOnly).field)
        assertEquals(SignInField.Password, SignInError.Api(ApiError.Unreachable).field)
    }

    @Test
    fun `editing a field clears the error`() = runTest {
        submit(password = "")

        viewModel.onPasswordChange("x")

        assertNull(viewModel.state.value.error)
    }
}
