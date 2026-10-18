package com.minthantzintun.trypost.feature.detail

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.PostChanges
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.download.DownloadRow
import com.minthantzintun.trypost.core.download.DownloadTracker
import com.minthantzintun.trypost.core.model.DownloadLink
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeDownloadRows
import com.minthantzintun.trypost.testing.FakeMediaDownloader
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.postJson
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testClient
import kotlinx.coroutines.CoroutineStart
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import java.time.Instant

class PostDetailViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var store: FakeSessionStore
    private val downloader = FakeMediaDownloader()
    private val downloadRows = FakeDownloadRows()

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
        PostDetailViewModel(SavedStateHandle(mapOf("id" to id)), PostsRepository(testClient(store), PostChanges()), downloader, DownloadTracker(downloadRows))

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
    fun `a media item downloads under its file name`() = runTest {
        server.enqueue(json(postJson("p1")))
        val viewModel = viewModel()
        val link = viewModel.state.first { !it.isLoading }.post?.media?.single()?.download

        assertEquals(DownloadLink("https://app.example.com/media/m1/download?signature=abc", "clip.mp4", "video/mp4"), link)
        downloadRows.rows = listOf(DownloadRow(id = 7, url = link!!.url, fileName = "clip.mp4", bytesSoFar = 30, totalBytes = 100))

        viewModel.download(link)

        assertEquals(30, viewModel.downloads.value.getValue(link.url).percent)

        assertEquals(listOf(link), downloader.started)
        assertEquals(DownloadOutcome.Started, viewModel.state.value.downloadOutcome)

        viewModel.dismissDownloadOutcome()
        assertNull(viewModel.state.value.downloadOutcome)
    }

    @Test
    fun `a refused download says so`() = runTest {
        server.enqueue(json(postJson("p1")))
        val viewModel = viewModel()
        val link = viewModel.state.first { !it.isLoading }.post!!.media.single().download!!
        downloader.accepts = false

        viewModel.download(link)

        assertEquals(DownloadOutcome.Failed, viewModel.state.value.downloadOutcome)
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

    @Test
    fun `rescheduling sends the time in utc and shows the scheduled post`() = runTest {
        server.enqueue(json(postJson("p1", status = "scheduled", scheduledAt = "2030-01-01T09:00:00+00:00")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }
        server.takeRequest()

        server.enqueue(json(postJson("p1", status = "scheduled", scheduledAt = "2030-03-04T05:06:00+00:00")))
        viewModel.reschedule(Instant.parse("2030-03-04T05:06:00Z"))
        val state = viewModel.state.first { !it.isSaving && it.post?.scheduledAt == Instant.parse("2030-03-04T05:06:00Z") }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/posts/p1/schedule", request.url.encodedPath)
        assertEquals("""{"scheduled_at":"2030-03-04T05:06:00Z"}""", request.body?.utf8())
        assertEquals(Instant.parse("2030-03-04T05:06:00Z"), state.post?.scheduledAt)
        assertNull(state.actionError)
    }

    @Test
    fun `a refused reschedule keeps the post and names the account`() = runTest {
        server.enqueue(json(postJson("p1", status = "scheduled")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }

        server.enqueue(
            json(
                """{"message":"Pick a privacy level.","errors":{"platforms.0.meta.privacy_level":["Pick a privacy level."]}}""",
                code = 422,
            ),
        )
        viewModel.reschedule(Instant.parse("2030-03-04T05:06:00Z"))
        val state = viewModel.state.first { !it.isSaving && it.actionError != null }

        assertEquals(PostStatus.Scheduled, state.post?.status)
        val error = state.actionError as ApiError.Validation
        val line = error.messagesFor(state.post!!).single()
        assertEquals("My Page", line.accountName)
        assertEquals("Pick a privacy level.", line.message)
        assertEquals("platforms.0.meta.privacy_level", line.field)
    }

    @Test
    fun `rescheduling tells the list`() = runTest {
        val changes = PostChanges()
        server.enqueue(json(postJson("p1", status = "scheduled", scheduledAt = "2030-01-01T09:00:00+00:00")))
        val viewModel = PostDetailViewModel(SavedStateHandle(mapOf("id" to "p1")), PostsRepository(testClient(store), changes), downloader, DownloadTracker(downloadRows))
        viewModel.state.first { !it.isLoading }
        server.takeRequest()

        val announced = async(start = CoroutineStart.UNDISPATCHED) { changes.posts.first() }
        server.enqueue(json(postJson("p1", status = "scheduled", scheduledAt = "2030-03-04T05:06:00+00:00")))
        viewModel.reschedule(Instant.parse("2030-03-04T05:06:00Z"))
        val state = viewModel.state.first { !it.isSaving && it.post?.scheduledAt == Instant.parse("2030-03-04T05:06:00Z") }

        assertEquals("PUT", server.takeRequest().method)
        assertEquals(Instant.parse("2030-03-04T05:06:00Z"), announced.await().scheduledAt)
        assertNull(state.actionError)
    }

    @Test
    fun `a post that moved on shows the server's reason and reloads`() = runTest {
        server.enqueue(json(postJson("p1", status = "scheduled")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }

        server.enqueue(json("""{"message":"This post has already been processed."}""", code = 409))
        server.enqueue(json(postJson("p1", status = "published", publishedAt = "2030-01-02T09:31:00+00:00")))
        viewModel.reschedule(Instant.parse("2030-03-04T05:06:00Z"))
        val state = viewModel.state.first { it.post?.status == PostStatus.Published && !it.isRefreshing }

        assertEquals(ApiError.Conflict("This post has already been processed."), state.actionError)
        assertEquals(3, server.requestCount)
    }

    @Test
    fun `sending to telegram shows the server's message and leaves the post`() = runTest {
        server.enqueue(json(postJson("p1", status = "published", publishedAt = "2030-01-02T09:31:00+00:00")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }
        server.takeRequest()

        server.enqueue(json("""{"message":"Sending to Telegram"}""", code = 202))
        viewModel.sendToTelegram()
        val state = viewModel.state.first { !it.isSendingToTelegram && it.telegramNotice != null }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/posts/p1/telegram", request.url.encodedPath)
        assertEquals("Sending to Telegram", state.telegramNotice)
        assertEquals(PostStatus.Published, state.post?.status)
        assertNull(state.telegramError)

        viewModel.dismissTelegramNotice()
        assertNull(viewModel.state.value.telegramNotice)
    }

    @Test
    fun `a refused send to telegram shows the server's reason`() = runTest {
        server.enqueue(json(postJson("p1")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }

        server.enqueue(
            json(
                """{"message":"Telegram is not set up.","errors":{"telegram":["Telegram is not set up."]}}""",
                code = 422,
            ),
        )
        viewModel.sendToTelegram()
        val state = viewModel.state.first { !it.isSendingToTelegram && it.telegramError != null }

        val error = state.telegramError as ApiError.Validation
        assertEquals(listOf("Telegram is not set up."), error.fields["telegram"])
        assertNull(state.telegramNotice)
        assertEquals(PostStatus.Scheduled, state.post?.status)
    }

    @Test
    fun `post now publishes the post and follows it until it settles`() = runTest {
        server.enqueue(json(postJson("p1", status = "scheduled")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }
        server.takeRequest()

        server.enqueue(json(postJson("p1", status = "publishing", scheduledAt = "2030-01-01T09:00:00+00:00")))
        server.enqueue(json(postJson("p1", status = "published", publishedAt = "2030-01-01T09:00:05+00:00")))
        viewModel.publishNow()
        val state = viewModel.state.first { it.post?.status == PostStatus.Published }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/posts/p1/publish", request.url.encodedPath)
        assertEquals("/mobile/v1/posts/p1", server.takeRequest().url.encodedPath)
        assertNull(state.actionError)
    }

    @Test
    fun `a refused post now keeps the post and names the account`() = runTest {
        server.enqueue(json(postJson("p1", status = "scheduled")))
        val viewModel = viewModel()
        viewModel.state.first { !it.isLoading }

        server.enqueue(
            json(
                """{"message":"Pick a privacy level.","errors":{"platforms.0.meta.privacy_level":["Pick a privacy level."]}}""",
                code = 422,
            ),
        )
        viewModel.publishNow()
        val state = viewModel.state.first { !it.isSaving && it.actionError != null }

        assertEquals(PostStatus.Scheduled, state.post?.status)
        val line = (state.actionError as ApiError.Validation).messagesFor(state.post!!).single()
        assertEquals("My Page", line.accountName)
        assertEquals("Pick a privacy level.", line.message)
    }
}
