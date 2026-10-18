package com.minthantzintun.trypost.feature.videos

import com.minthantzintun.trypost.core.data.VideosRepository
import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.model.DownloadLink
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.BytesVideoSource
import com.minthantzintun.trypost.testing.FakeMediaDownloader
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import com.minthantzintun.trypost.testing.videoJson
import com.minthantzintun.trypost.testing.videoUploadStarted
import com.minthantzintun.trypost.testing.videosPage
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.OkHttpClient
import org.junit.After
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import java.time.Instant

class VideosViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var store: FakeSessionStore
    private val downloader = FakeMediaDownloader()

    @Before
    fun setUp() {
        server.start()
        store = FakeSessionStore(server.session())
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): VideosViewModel = VideosViewModel(VideosRepository(testClient(store), OkHttpClient()), downloader)

    private suspend fun VideosViewModel.settled(): VideosUiState =
        state.first { !it.isLoading && !it.isRefreshing && !it.isLoadingMore && !it.isUploading }

    @Test
    fun `the videos load newest first with their name, size, length and date`() = runTest {
        server.enqueue(videosPage(listOf(videoJson("v2", name = "Newer"), videoJson("v1", size = null, duration = null))))

        val state = viewModel().settled()

        val request = server.takeRequest()
        assertEquals("/mobile/v1/videos", request.url.encodedPath)
        assertEquals("1", request.url.queryParameter("page"))
        assertEquals("Bearer good-token", request.headers["Authorization"])
        assertEquals(listOf("v2", "v1"), state.videos.map { it.id })
        val newer = state.videos.first()
        assertEquals("Newer", newer.name)
        assertEquals(5_242_880L, newer.size)
        assertEquals(75.5, newer.durationSeconds!!, 0.0)
        assertEquals(Instant.parse("2030-01-02T09:30:00Z"), newer.createdAt)
        assertNull(state.videos.last().size)
        assertNull(state.videos.last().durationSeconds)
    }

    @Test
    fun `a video downloads under its name`() = runTest {
        server.enqueue(videosPage(listOf(videoJson("v1", name = "Beach day"), videoJson("v2", downloadUrl = null))))
        val viewModel = viewModel()
        val videos = viewModel.settled().videos

        assertEquals(DownloadLink("https://app.example.com/media/f-v1/download?signature=abc", "Beach day.mp4", null), videos[0].download)
        assertNull(videos[1].download)

        viewModel.download(videos[0].download!!)

        assertEquals(listOf(videos[0].download), downloader.started)
        assertEquals(DownloadOutcome.Started, viewModel.state.value.downloadOutcome)

        downloader.accepts = false
        viewModel.download(videos[0].download!!)

        assertEquals(DownloadOutcome.Failed, viewModel.state.value.downloadOutcome)
        viewModel.dismissDownloadOutcome()
        assertNull(viewModel.state.value.downloadOutcome)
    }

    @Test
    fun `scrolling loads the next page and appends it`() = runTest {
        server.enqueue(videosPage(listOf(videoJson("v1")), page = 1, lastPage = 2))
        server.enqueue(videosPage(listOf(videoJson("v1"), videoJson("v2")), page = 2, lastPage = 2))
        val viewModel = viewModel()
        assertTrue(viewModel.settled().hasMore)

        viewModel.loadMore()
        val state = viewModel.state.first { it.page == 2 && !it.isLoadingMore }

        assertEquals(listOf("v1", "v2"), state.videos.map { it.id })
        assertFalse(state.hasMore)
        server.takeRequest()
        assertEquals("2", server.takeRequest().url.queryParameter("page"))
    }

    @Test
    fun `picking a video asks for a name that defaults to the file name`() = runTest {
        server.enqueue(videosPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled()

        viewModel.pickVideo(BytesVideoSource(fileName = "Beach day.MP4"))

        assertEquals("Beach day", viewModel.state.value.pending?.name)

        viewModel.cancelUpload()
        assertNull(viewModel.state.value.pending)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `uploading puts the file at the presigned url, registers it with its name and reloads the list`() = runTest {
        server.enqueue(videosPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled()
        val source = BytesVideoSource()
        server.enqueue(server.videoUploadStarted("up-1"))
        server.enqueue(MockResponse.Builder().code(200).build())
        server.enqueue(json(videoJson("v1", name = "My clip"), code = 201))
        server.enqueue(videosPage(listOf(videoJson("v1", name = "My clip"))))

        viewModel.pickVideo(source)
        viewModel.upload("My clip")
        val state = viewModel.state.first { it.videos.isNotEmpty() && !it.isUploading }

        assertEquals(listOf("My clip"), state.videos.map { it.name })
        assertNull(state.pending)
        assertNull(state.uploadError)

        server.takeRequest()
        val start = server.takeRequest()
        assertEquals("/mobile/v1/videos/uploads", start.url.encodedPath)
        val startBody = testJson.parseToJsonElement(start.body!!.utf8()).jsonObject
        assertEquals("Beach day.mp4", startBody.getValue("file_name").jsonPrimitive.content)
        assertEquals(source.size.toString(), startBody.getValue("size").jsonPrimitive.content)

        val put = server.takeRequest()
        assertEquals("PUT", put.method)
        assertEquals("/bucket/raw.mp4", put.url.encodedPath)
        assertEquals("video/mp4", put.headers["Content-Type"])
        assertNull(put.headers["Authorization"])
        assertArrayEquals(source.open().readBytes(), put.body!!.toByteArray())

        val register = server.takeRequest()
        assertEquals("/mobile/v1/videos", register.url.encodedPath)
        val registerBody = testJson.parseToJsonElement(register.body!!.utf8()).jsonObject
        assertEquals("My clip", registerBody.getValue("name").jsonPrimitive.content)
        assertEquals("up-1", registerBody.getValue("upload_id").jsonPrimitive.content)

        assertEquals("/mobile/v1/videos", server.takeRequest().url.encodedPath)
    }

    @Test
    fun `a refused upload shows the server's reason and keeps the name prompt`() = runTest {
        server.enqueue(videosPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled()
        server.enqueue(json("""{"message":"Choose a video file.","errors":{"file_name":["Choose a video file."]}}""", code = 422))

        viewModel.pickVideo(BytesVideoSource())
        viewModel.upload("My clip")
        val state = viewModel.state.first { it.uploadError != null }

        assertEquals(ApiError.Validation("Choose a video file.", mapOf("file_name" to listOf("Choose a video file."))), state.uploadError)
        assertFalse(state.isUploading)
        assertEquals("My clip", state.pending?.name)
        assertEquals(2, server.requestCount)
    }

    @Test
    fun `a failed put is shown and nothing is registered`() = runTest {
        server.enqueue(videosPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled()
        server.enqueue(server.videoUploadStarted())
        server.enqueue(MockResponse.Builder().code(403).body("<Error>SignatureDoesNotMatch</Error>").build())

        viewModel.pickVideo(BytesVideoSource())
        viewModel.upload("My clip")
        val state = viewModel.state.first { it.uploadError != null }

        assertEquals(ApiError.Server(403), state.uploadError)
        assertEquals(3, server.requestCount)
        assertTrue(state.videos.isEmpty())
    }

    @Test
    fun `deleting a video removes it from the list`() = runTest {
        server.enqueue(videosPage(listOf(videoJson("v1"), videoJson("v2"))))
        val viewModel = viewModel()
        viewModel.settled()
        server.enqueue(MockResponse.Builder().code(204).build())

        viewModel.delete("v1")
        val state = viewModel.state.first { it.videos.size == 1 }

        assertEquals(listOf("v2"), state.videos.map { it.id })
        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/mobile/v1/videos/v1", request.url.encodedPath)
    }

    @Test
    fun `a failed delete keeps the video and shows the error`() = runTest {
        server.enqueue(videosPage(listOf(videoJson("v1"))))
        val viewModel = viewModel()
        viewModel.settled()
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))

        viewModel.delete("v1")
        val state = viewModel.state.first { it.deleteError != null }

        assertEquals(ApiError.Server(500), state.deleteError)
        assertEquals(listOf("v1"), state.videos.map { it.id })
        assertNull(state.deletingId)
    }
}
