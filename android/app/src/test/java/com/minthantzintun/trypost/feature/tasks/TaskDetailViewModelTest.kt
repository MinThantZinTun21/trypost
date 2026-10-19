package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class TaskDetailViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var repository: TasksRepository

    @Before
    fun setUp() {
        server.start()
        repository = TasksRepository(testClient(FakeSessionStore(server.session())))
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): TaskDetailViewModel = TaskDetailViewModel(SavedStateHandle(mapOf("id" to "t1")), repository)

    private suspend fun TaskDetailViewModel.loaded(): TaskDetailUiState = state.first { it.task != null && !it.isLoading }

    @Test
    fun `Done goes through the status endpoint and shows the saved Task`() = runTest {
        server.enqueue(json(taskJson("t1")))
        server.enqueue(json(taskJson("t1", status = "done")))
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.setStatus(TaskStatus.Done)
        val state = viewModel.state.first { it.task?.status == TaskStatus.Done && it.savingStatus == null }

        assertEquals("/mobile/v1/tasks/t1", server.takeRequest().url.encodedPath)
        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/tasks/t1/status", request.url.encodedPath)
        assertEquals("done", testJson.parseToJsonElement(request.body!!.utf8()).jsonObject.getValue("status").jsonPrimitive.content)
        assertTrue(state.task!!.completedAt != null)
    }

    @Test
    fun `Reopen and In progress send their own statuses`() = runTest {
        server.enqueue(json(taskJson("t1", status = "done")))
        server.enqueue(json(taskJson("t1", status = "todo")))
        server.enqueue(json(taskJson("t1", status = "in_progress")))
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.setStatus(TaskStatus.Todo)
        viewModel.state.first { it.task?.status == TaskStatus.Todo && it.savingStatus == null }
        viewModel.setStatus(TaskStatus.InProgress)
        viewModel.state.first { it.task?.status == TaskStatus.InProgress && it.savingStatus == null }

        server.takeRequest()
        assertEquals("todo", testJson.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject.getValue("status").jsonPrimitive.content)
        assertEquals("in_progress", testJson.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject.getValue("status").jsonPrimitive.content)
    }

    @Test
    fun `asking for the status the Task already has sends nothing`() = runTest {
        server.enqueue(json(taskJson("t1", status = "todo")))
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.setStatus(TaskStatus.Todo)

        assertNull(viewModel.state.value.savingStatus)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `a refused status change keeps the Task and shows why`() = runTest {
        server.enqueue(json(taskJson("t1")))
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.setStatus(TaskStatus.Done)
        val state = viewModel.state.first { it.actionError != null }

        assertEquals(TaskStatus.Todo, state.task!!.status)
        assertNull(state.savingStatus)
        assertTrue(state.actionError is ApiError.Server)

        viewModel.dismissActionError()
        assertNull(viewModel.state.value.actionError)
    }

    @Test
    fun `delete removes the Task and closes the screen`() = runTest {
        server.enqueue(json(taskJson("t1")))
        server.enqueue(noContent())
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.delete()
        viewModel.state.first { it.isDeleted }

        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/mobile/v1/tasks/t1", request.url.encodedPath)
    }

    @Test
    fun `a failed delete stays on the Task`() = runTest {
        server.enqueue(json(taskJson("t1")))
        server.enqueue(json("""{"message":"Not Found"}""", code = 404))
        val viewModel = viewModel()
        viewModel.loaded()

        viewModel.delete()
        val state = viewModel.state.first { it.actionError != null }

        assertFalse(state.isDeleted)
        assertFalse(state.isDeleting)
        assertEquals(ApiError.NotFound, state.actionError)
    }

    @Test
    fun `an edit saved elsewhere shows without a reload`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Old")))
        server.enqueue(json(taskJson("t1", title = "New")))
        val viewModel = viewModel()
        viewModel.loaded()

        repository.setStatus("t1", TaskStatus.Todo)

        assertEquals("New", viewModel.state.first { it.task?.title == "New" }.task!!.title)
        assertEquals(2, server.requestCount)
    }

    @Test
    fun `a Task that will not load shows the error`() = runTest {
        server.enqueue(json("""{"message":"Not Found"}""", code = 404))

        val state = viewModel().state.first { it.error != null }

        assertNull(state.task)
        assertEquals(ApiError.NotFound, state.error)
    }
}
