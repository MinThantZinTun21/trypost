package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.taskTagRefJson
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import com.minthantzintun.trypost.testing.validationError
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.boolean
import kotlinx.serialization.json.jsonArray
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
import java.time.Clock
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.ZoneId

class TaskEditorViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var repository: TasksRepository

    /** 10:20 on 1 Jan 2030 in Yangon (UTC+06:30). */
    private val clock = Clock.fixed(Instant.parse("2030-01-01T03:50:00Z"), ZoneId.of("Asia/Yangon"))

    @Before
    fun setUp() {
        server.start()
        repository = TasksRepository(testClient(FakeSessionStore(server.session())))
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun newTask(): TaskEditorViewModel = TaskEditorViewModel(SavedStateHandle(), repository, clock)

    private fun editTask(id: String = "t1"): TaskEditorViewModel = TaskEditorViewModel(SavedStateHandle(mapOf("id" to id)), repository, clock)

    private suspend fun TaskEditorViewModel.loaded(): TaskEditorUiState = state.first { it.isReady && !it.isLoading }

    private suspend fun TaskEditorViewModel.saved(): TaskEditorUiState = state.first { !it.isSaving }

    @Test
    fun `a new Task is created with its due in the phone's timezone`() = runTest {
        server.enqueue(json(taskJson("t1"), code = 201))
        val viewModel = newTask()

        viewModel.changeTitle("Call the supplier")
        viewModel.changeDescription("Ask about the price")
        viewModel.changePriority(TaskPriority.Medium)
        viewModel.changeDueKind(DueKind.DateTime)
        viewModel.changeDueDate(LocalDate.of(2030, 1, 5))
        viewModel.changeDueTime(LocalTime.of(15, 0))
        viewModel.toggleTaskTag("k1")
        viewModel.save()
        val state = viewModel.state.first { it.isSaved }

        assertFalse(state.hasUnsavedChanges)
        val request = server.takeRequest()
        assertEquals("POST", request.method)
        val body = testJson.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("Call the supplier", body.getValue("title").jsonPrimitive.content)
        assertEquals("Ask about the price", body.getValue("description").jsonPrimitive.content)
        assertEquals("2", body.getValue("priority").jsonPrimitive.content)
        assertEquals("2030-01-05T15:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertFalse(body.getValue("due_all_day").jsonPrimitive.boolean)
        assertEquals(listOf("k1"), body.getValue("task_tags").jsonArray.map { it.jsonPrimitive.content })
    }

    @Test
    fun `a date due starts today and a time starts at the next full hour`() = runTest {
        val viewModel = newTask()

        viewModel.changeDueKind(DueKind.Date)
        assertEquals(TaskDue.AllDay(LocalDate.of(2030, 1, 1)), viewModel.state.value.form.due)

        viewModel.changeDueKind(DueKind.DateTime)
        assertEquals(TaskDue.Timed(LocalDateTime.of(2030, 1, 1, 11, 0)), viewModel.state.value.form.due)

        viewModel.changeDueDate(LocalDate.of(2030, 2, 3))
        assertEquals(TaskDue.Timed(LocalDateTime.of(2030, 2, 3, 11, 0)), viewModel.state.value.form.due)

        viewModel.changeDueKind(DueKind.Date)
        assertEquals(TaskDue.AllDay(LocalDate.of(2030, 2, 3)), viewModel.state.value.form.due)

        viewModel.changeDueKind(DueKind.None)
        assertNull(viewModel.state.value.form.due)
    }

    @Test
    fun `an all-day due is sent as the start of the day`() = runTest {
        server.enqueue(json(taskJson("t1"), code = 201))
        val viewModel = newTask()
        viewModel.changeTitle("Pay rent")
        viewModel.changeDueKind(DueKind.Date)

        viewModel.save()
        viewModel.state.first { it.isSaved }

        val body = testJson.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        assertEquals("2030-01-01T00:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertTrue(body.getValue("due_all_day").jsonPrimitive.boolean)
    }

    @Test
    fun `the server's messages show under their fields`() = runTest {
        server.enqueue(
            validationError(
                "title" to "The title field is required.",
                "due_at" to "The due must include its offset.",
                "task_tags.1" to "The selected tag is invalid.",
            ),
        )
        val viewModel = newTask()

        viewModel.save()
        val state = viewModel.saved()

        assertEquals("The title field is required.", state.fieldErrors[TaskField.Title])
        assertEquals("The due must include its offset.", state.fieldErrors[TaskField.Due])
        assertEquals("The selected tag is invalid.", state.fieldErrors[TaskField.TaskTags])
        assertNull(state.saveError)
        assertFalse(state.isSaved)
    }

    @Test
    fun `a message about no known field shows above the form`() = runTest {
        server.enqueue(validationError("status" to "Nope."))
        val viewModel = newTask()

        viewModel.save()
        val state = viewModel.saved()

        assertTrue(state.saveError is ApiError.Validation)
        assertTrue(state.fieldErrors.isEmpty())
    }

    @Test
    fun `a lost connection shows above the form`() = runTest {
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        val viewModel = newTask()

        viewModel.save()

        assertTrue(viewModel.saved().saveError is ApiError.Server)
    }

    @Test
    fun `changing a field clears only its own error`() = runTest {
        server.enqueue(validationError("title" to "Required.", "description" to "Too long."))
        val viewModel = newTask()
        viewModel.save()
        viewModel.saved()

        viewModel.changeTitle("Now with a title")

        assertNull(viewModel.state.value.fieldErrors[TaskField.Title])
        assertEquals("Too long.", viewModel.state.value.fieldErrors[TaskField.Description])
    }

    @Test
    fun `editing loads the Task and asks before leaving only after a change`() = runTest {
        server.enqueue(
            json(
                taskJson(
                    "t1",
                    title = "Call",
                    priority = 3,
                    dueAt = "2030-01-01T17:30:00+00:00",
                    dueAllDay = true,
                    taskTags = listOf(taskTagRefJson("k1")),
                ),
            ),
        )
        val viewModel = editTask()

        val loaded = viewModel.loaded()
        assertEquals("/mobile/v1/tasks/t1", server.takeRequest().url.encodedPath)
        assertEquals("Call", loaded.form.title)
        assertEquals(TaskPriority.High, loaded.form.priority)
        assertEquals(TaskDue.AllDay(LocalDate.of(2030, 1, 2)), loaded.form.due)
        assertEquals(listOf("k1"), loaded.form.taskTagIds)
        assertFalse(loaded.hasUnsavedChanges)

        viewModel.changeTitle("Call again")
        assertTrue(viewModel.state.value.hasUnsavedChanges)

        viewModel.changeTitle("Call")
        assertFalse(viewModel.state.value.hasUnsavedChanges)
    }

    @Test
    fun `saving an edited Task replaces it`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call", description = "Old")))
        server.enqueue(json(taskJson("t1", title = "Call")))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.changeDescription("")
        viewModel.save()
        viewModel.state.first { it.isSaved }

        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/tasks/t1", request.url.encodedPath)
        val body = testJson.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertFalse(body.containsKey("description"))
        assertFalse(body.containsKey("due_at"))
    }

    @Test
    fun `a Task that will not load can be retried`() = runTest {
        server.enqueue(json("""{"message":"Not Found"}""", code = 404))
        server.enqueue(json(taskJson("t1", title = "Back")))
        val viewModel = editTask()

        assertEquals(ApiError.NotFound, viewModel.state.first { it.loadError != null }.loadError)
        assertFalse(viewModel.state.value.isReady)

        viewModel.retry()

        assertEquals("Back", viewModel.loaded().form.title)
    }

    @Test
    fun `a deleted Task tag leaves the form without counting as a change`() = runTest {
        server.enqueue(json(taskJson("t1", taskTags = listOf(taskTagRefJson("k1"), taskTagRefJson("k2")))))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.keepOnlyKnownTaskTags(setOf("k2"))

        assertEquals(listOf("k2"), viewModel.state.value.form.taskTagIds)
        assertFalse(viewModel.state.value.hasUnsavedChanges)
    }

    @Test
    fun `validation keys map to the editor's fields`() {
        assertEquals(TaskField.Title, taskFieldForErrorKey("title"))
        assertEquals(TaskField.Due, taskFieldForErrorKey("due_all_day"))
        assertEquals(TaskField.TaskTags, taskFieldForErrorKey("task_tags.3"))
        assertEquals(TaskField.Priority, taskFieldForErrorKey("priority"))
        assertNull(taskFieldForErrorKey("status"))
    }
}
