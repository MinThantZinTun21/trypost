package com.minthantzintun.trypost.feature.edit

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.PostChanges
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.model.PlatformText
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class EditPostViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var store: FakeSessionStore

    @Before
    fun setUp() {
        server.start()
        store = FakeSessionStore(server.session())
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): EditPostViewModel =
        EditPostViewModel(SavedStateHandle(mapOf("id" to "p1")), PostsRepository(testClient(store), PostChanges()))

    private fun post(content: String = "Hello", caption: String = "Old caption"): String = """
        {"id":"p1","status":"scheduled","content":"$content","scheduled_at":"2030-01-02T09:30:00+00:00","editable":true,"media":[],
         "platforms":[
           {"id":"fb","platform":"facebook","content_type":"facebook_post","status":"pending","meta":{},"text_fields":[],
            "social_account":{"display_name":"My Page"}},
           {"id":"tt","platform":"tiktok","content_type":"tiktok_video","status":"pending",
            "meta":{"privacy_level":"SELF_ONLY","caption":"$caption"},"text_fields":["caption"],
            "social_account":{"display_name":"My TikTok"}},
           {"id":"yt","platform":"youtube","content_type":"youtube_short","status":"pending",
            "meta":{"title":"Old title","tags":["a","b"]},"text_fields":["title","description","tags"],
            "social_account":{"display_name":"My Channel"}}]}
    """.trimIndent()

    private suspend fun EditPostViewModel.loaded(): EditPostUiState = state.first { !it.isLoading }

    @Test
    fun `each account offers only the texts its content type publishes`() = runTest {
        server.enqueue(json(post()))

        val state = viewModel().loaded()

        assertEquals("Hello", state.content)
        assertEquals(emptyMap<PlatformText, String>(), state.texts["fb"])
        assertEquals(mapOf(PlatformText.Caption to "Old caption"), state.texts["tt"])
        assertEquals(
            mapOf(PlatformText.Title to "Old title", PlatformText.Description to "", PlatformText.Tags to "a, b"),
            state.texts["yt"],
        )
    }

    @Test
    fun `saving sends every account in order with its texts`() = runTest {
        server.enqueue(json(post()))
        val viewModel = viewModel()
        viewModel.loaded()
        server.takeRequest()

        viewModel.changeContent("New content")
        viewModel.changeText("tt", PlatformText.Caption, "New caption")
        viewModel.changeText("yt", PlatformText.Tags, " one, ,two ")
        server.enqueue(json(post(content = "New content", caption = "New caption")))
        viewModel.save()
        val state = viewModel.state.first { it.isSaved }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/posts/p1", request.url.encodedPath)
        val body = testJson.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("New content", body.getValue("content").jsonPrimitive.content)
        val platforms = body.getValue("platforms").jsonArray.map { it.jsonObject }
        assertEquals(listOf("fb", "tt", "yt"), platforms.map { it.getValue("id").jsonPrimitive.content })
        assertEquals("New caption", platforms[1].getValue("meta").jsonObject.getValue("caption").jsonPrimitive.content)
        val youtube = platforms[2].getValue("meta").jsonObject
        assertEquals(listOf("one", "two"), youtube.getValue("tags").jsonArray.map { it.jsonPrimitive.content })
        assertEquals(JsonNull, youtube.getValue("description"))
        assertEquals("New content", state.post?.content)
    }

    @Test
    fun `validation errors land under their fields`() = runTest {
        server.enqueue(json(post()))
        val viewModel = viewModel()
        viewModel.loaded()

        server.enqueue(
            json(
                """{"message":"Too long.","errors":{"platforms.2.meta.title":["The title is too long."],"content":["Too long."]}}""",
                code = 422,
            ),
        )
        viewModel.save()
        val state = viewModel.state.first { !it.isSaving && it.fieldErrors.isNotEmpty() }

        assertEquals("The title is too long.", state.fieldErrors[EditField.Text("yt", PlatformText.Title)])
        assertEquals("Too long.", state.fieldErrors[EditField.Content])
        assertNull(state.saveError)

        viewModel.changeText("yt", PlatformText.Title, "Short")
        assertNull(viewModel.state.value.fieldErrors[EditField.Text("yt", PlatformText.Title)])
    }

    @Test
    fun `a post that moved on says why and is not saved`() = runTest {
        server.enqueue(json(post()))
        val viewModel = viewModel()
        viewModel.loaded()

        server.enqueue(json("""{"message":"This post has already been processed."}""", code = 409))
        viewModel.save()
        val state = viewModel.state.first { !it.isSaving && it.saveError != null }

        assertEquals(ApiError.Conflict("This post has already been processed."), state.saveError)
        assertTrue(!state.isSaved)
    }

    @Test
    fun `untouched content goes back as stored`() = runTest {
        server.enqueue(json(post(content = "<p>Hello</p>")))
        val viewModel = viewModel()
        assertEquals("Hello", viewModel.loaded().content)
        server.takeRequest()

        server.enqueue(json(post(content = "<p>Hello</p>")))
        viewModel.save()
        viewModel.state.first { it.isSaved }

        val body = testJson.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        assertEquals("<p>Hello</p>", body.getValue("content").jsonPrimitive.content)
    }
}
