package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.testing.BytesUploadSource
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.videoJson
import com.minthantzintun.trypost.testing.videoUploadStarted
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.OkHttpClient
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

class VideosRepositoryTest {
    private val server = MockWebServer()

    @Before
    fun setUp() {
        server.start()
    }

    @After
    fun tearDown() {
        server.close()
    }

    @Test
    fun `the upload reports its progress up to the whole file`() = runTest {
        val repository = VideosRepository(testClient(FakeSessionStore(server.session())), PresignedUploader(OkHttpClient()))
        server.enqueue(server.videoUploadStarted())
        server.enqueue(MockResponse.Builder().code(200).build())
        server.enqueue(json(videoJson("v1"), code = 201))
        val progress = mutableListOf<Float>()

        val result = repository.upload(BytesUploadSource(), "My clip") { progress += it }

        assertTrue(result is ApiResult.Success)
        assertTrue(progress.size > 1)
        assertEquals(progress.sorted(), progress)
        assertEquals(1f, progress.last())
    }
}
