package com.minthantzintun.trypost.feature.detail

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.postJson
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testClient
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class PostDetailViewModelTest {
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

    private fun viewModel(id: String = "p1"): PostDetailViewModel =
        PostDetailViewModel(SavedStateHandle(mapOf("id" to id)), PostsRepository(testClient(store)))

    @Test
    fun `it loads the post by its id`() = runTest {
        server.enqueue(json(postJson("p1", content = "Hello")))

        val state = viewModel().state.first { !it.isLoading }

        assertEquals("/mobile/v1/posts/p1", server.takeRequest().url.encodedPath)
        assertEquals("Hello", state.post?.content)
        assertEquals("My Page", state.post?.platforms?.single()?.accountName)
        assertNull(state.error)
    }

    @Test
    fun `a publishing post reloads until it settles, then stops`() = runTest {
        server.enqueue(json(postJson("p1", status = "publishing")))
        server.enqueue(json(postJson("p1", status = "publishing")))
        server.enqueue(json(postJson("p1", status = "published", publishedAt = "2030-01-02T09:31:00+00:00")))

        val viewModel = viewModel()
        val state = viewModel.state.first { it.post?.status == PostStatus.Published }

        assertEquals(3, server.requestCount)
        assertEquals(PostStatus.Published, state.post?.status)

        server.enqueue(json(postJson("p1", status = "published")))
        viewModel.refresh()
        viewModel.state.first { !it.isRefreshing }
        assertEquals(4, server.requestCount)
    }

    @Test
    fun `a missing post shows the error and retry loads it again`() = runTest {
        server.enqueue(json("""{"message":"Not Found."}""", code = 404))
        server.enqueue(json(postJson("p1")))

        val viewModel = viewModel()
        val failed = viewModel.state.first { !it.isLoading }
        assertEquals(ApiError.NotFound, failed.error)
        assertNull(failed.post)

        viewModel.retry()
        val loaded = viewModel.state.first { it.post != null }
        assertNull(loaded.error)
    }

    @Test
    fun `a revoked token signs out`() = runTest {
        server.enqueue(json("""{"message":"Unauthenticated."}""", code = 401))

        val state = viewModel().state.first { !it.isLoading }

        assertEquals(ApiError.Unauthorized, state.error)
        assertNull(store.current())
    }
}
