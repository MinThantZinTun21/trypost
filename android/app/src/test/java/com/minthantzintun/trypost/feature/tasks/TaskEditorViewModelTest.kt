package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.SavedStateHandle
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.BlockKind
import com.minthantzintun.trypost.core.model.InlineStyle
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.ReminderTiming
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.reminderJson
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.taskTagRefJson
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import com.minthantzintun.trypost.testing.validationError
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.boolean
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockWebServer
import mockwebserver3.RecordedRequest
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

    private suspend fun TaskEditorViewModel.closed(): TaskEditorUiState = state.first { it.isClosed }

    /** Types [text] into the body's first line, as its field reports it. */
    private fun TaskEditorViewModel.typeInBody(text: String) {
        changeBlockText(state.value.body.blocks.first().id, text, text.length)
    }

    private fun RecordedRequest.json(): JsonObject = testJson.parseToJsonElement(body!!.utf8()).jsonObject

    @Test
    fun `Back creates a new Task with its due in the phone's timezone`() = runTest {
        server.enqueue(json(taskJson("t1"), code = 201))
        val viewModel = newTask()

        viewModel.changeTitle("Call the supplier")
        viewModel.typeInBody("Ask about the price")
        viewModel.changePriority(TaskPriority.Medium)
        viewModel.changeDueKind(DueKind.DateTime)
        viewModel.changeDueDate(LocalDate.of(2030, 1, 5))
        viewModel.changeDueTime(LocalTime.of(15, 0))
        viewModel.toggleTaskTag("k1")
        viewModel.leave()
        val state = viewModel.closed()

        assertFalse(state.hasUnsavedChanges)
        assertFalse(state.isDeleted)
        val request = server.takeRequest()
        assertEquals("POST", request.method)
        val body = request.json()
        assertEquals("Call the supplier", body.getValue("title").jsonPrimitive.content)
        assertEquals("Ask about the price", body.getValue("description").jsonPrimitive.content)
        assertEquals("2", body.getValue("priority").jsonPrimitive.content)
        assertEquals("2030-01-05T15:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertFalse(body.getValue("due_all_day").jsonPrimitive.boolean)
        assertEquals(listOf("k1"), body.getValue("task_tags").jsonArray.map { it.jsonPrimitive.content })
    }

    @Test
    fun `a new Task with no title and no body is dropped without a request`() = runTest {
        val viewModel = newTask()
        viewModel.changePriority(TaskPriority.High)
        viewModel.typeInBody("   ")

        viewModel.leave()

        assertTrue(viewModel.state.value.isClosed)
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `a body without a title is saved, formatting and all`() = runTest {
        server.enqueue(json(taskJson("t1", title = null), code = 201))
        val viewModel = newTask()

        viewModel.typeInBody("Milk")
        viewModel.selectInBody(0, 0, 4)
        viewModel.toggleStyle(InlineStyle.Bold)
        viewModel.toggleBlockKind(BlockKind.Checkbox)
        viewModel.changeBlockText(0, "Milk\n", 5)
        viewModel.changeBlockText(1, "Eggs", 4)
        viewModel.leave()
        viewModel.closed()

        val body = server.takeRequest().json()
        assertFalse("title" in body)
        assertEquals("- [ ] **Milk**\n- [ ] Eggs", body.getValue("description").jsonPrimitive.content)
    }

    @Test
    fun `New checklist starts on an empty checkbox`() = runTest {
        val viewModel = TaskEditorViewModel(SavedStateHandle(mapOf("startsAsChecklist" to true)), repository, clock)

        val body = viewModel.state.value.body
        assertEquals(listOf(BlockKind.Checkbox), body.blocks.map { it.kind })
        assertEquals(EditorSelection(0, 0), body.selection)
        assertTrue(viewModel.state.value.isEmpty)
    }

    @Test
    fun `the pin and the colour are sent with the Task`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Trip")))
        server.enqueue(json(taskJson("t1", title = "Trip", color = "teal", pinned = true)))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.togglePin()
        viewModel.changeColor(TagColor.Teal)
        viewModel.leave()
        viewModel.closed()

        server.takeRequest()
        val body = server.takeRequest().json()
        assertEquals("teal", body.getValue("color").jsonPrimitive.content)
        assertTrue(body.getValue("pinned").jsonPrimitive.boolean)
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

        viewModel.leave()
        viewModel.closed()

        val body = server.takeRequest().json()
        assertEquals("2030-01-01T00:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertTrue(body.getValue("due_all_day").jsonPrimitive.boolean)
    }

    @Test
    fun `a refused save stays open with the messages under their fields`() = runTest {
        server.enqueue(
            validationError(
                "title" to "The title may not be greater than 255 characters.",
                "due_at" to "The due must include its offset.",
                "task_tags.1" to "The selected tag is invalid.",
            ),
        )
        val viewModel = newTask()
        viewModel.changeTitle("Long")

        viewModel.leave()
        val state = viewModel.saved()

        assertEquals("The title may not be greater than 255 characters.", state.fieldErrors[TaskField.Title])
        assertEquals("The due must include its offset.", state.fieldErrors[TaskField.Due])
        assertEquals("The selected tag is invalid.", state.fieldErrors[TaskField.TaskTags])
        assertNull(state.saveError)
        assertFalse(state.isClosed)
        assertTrue(state.hasUnsavedChanges)
    }

    @Test
    fun `a message about no known field shows above the form`() = runTest {
        server.enqueue(validationError("status" to "Nope."))
        val viewModel = newTask()
        viewModel.changeTitle("Call")

        viewModel.leave()
        val state = viewModel.saved()

        assertTrue(state.saveError is ApiError.Validation)
        assertTrue(state.fieldErrors.isEmpty())
        assertFalse(state.isClosed)
    }

    @Test
    fun `a lost connection shows above the form`() = runTest {
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        val viewModel = newTask()
        viewModel.changeTitle("Call")

        viewModel.leave()

        val state = viewModel.saved()
        assertTrue(state.saveError is ApiError.Server)
        assertFalse(state.isClosed)
    }

    @Test
    fun `changing a field clears only its own error`() = runTest {
        server.enqueue(validationError("title" to "Too long.", "description" to "Too long."))
        val viewModel = newTask()
        viewModel.changeTitle("Call")
        viewModel.leave()
        viewModel.saved()

        viewModel.changeTitle("Now shorter")

        assertNull(viewModel.state.value.fieldErrors[TaskField.Title])
        assertEquals("Too long.", viewModel.state.value.fieldErrors[TaskField.Description])

        viewModel.typeInBody("Shorter")

        assertNull(viewModel.state.value.fieldErrors[TaskField.Description])
    }

    @Test
    fun `editing loads the Task with its formatting and counts only a real change`() = runTest {
        server.enqueue(
            json(
                taskJson(
                    "t1",
                    title = "Call",
                    description = "# Plan\n5. **Ring** them",
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
        assertEquals(listOf("Plan", "Ring them"), loaded.body.blocks.map { it.text.text })
        assertEquals(listOf(BlockKind.Heading1, BlockKind.Numbered), loaded.body.blocks.map { it.kind })
        assertFalse(loaded.hasUnsavedChanges)

        // Tapping into the body is not a change, though the list numbers from 1.
        viewModel.selectInBody(1, 2)
        assertFalse(viewModel.state.value.hasUnsavedChanges)

        viewModel.changeTitle("Call again")
        assertTrue(viewModel.state.value.hasUnsavedChanges)

        viewModel.changeTitle("Call")
        assertFalse(viewModel.state.value.hasUnsavedChanges)
    }

    @Test
    fun `Back on an untouched Task closes without a request`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call")))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.leave()

        assertTrue(viewModel.state.value.isClosed)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `Back saves an edited Task in place`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call", description = "Old")))
        server.enqueue(json(taskJson("t1", title = "Call")))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.changeBlockText(0, "", 0)
        viewModel.leave()
        viewModel.closed()

        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/tasks/t1", request.url.encodedPath)
        val body = request.json()
        assertFalse(body.containsKey("description"))
        assertFalse(body.containsKey("due_at"))
    }

    @Test
    fun `Discard changes closes without saving`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call")))
        val viewModel = editTask()
        viewModel.loaded()
        viewModel.changeTitle("Something else")

        viewModel.discard()

        assertTrue(viewModel.state.value.isClosed)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `Delete removes the stored Task and leaves`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call")))
        server.enqueue(noContent())
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.delete()
        val state = viewModel.state.first { it.isDeleted }

        assertTrue(state.isClosed)
        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("DELETE /mobile/v1/tasks/t1", "${request.method} ${request.url.encodedPath}")
    }

    @Test
    fun `a failed delete stays open with the reason`() = runTest {
        server.enqueue(json(taskJson("t1", title = "Call")))
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        val viewModel = editTask()
        viewModel.loaded()

        viewModel.delete()
        val state = viewModel.state.first { it.saveError != null }

        assertTrue(state.saveError is ApiError.Server)
        assertFalse(state.isClosed)
        assertFalse(state.isDeleting)
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

    @Test
    fun `reminders are sent with the Task, and those before the due go when the due does`() = runTest {
        server.enqueue(json(taskJson("t1"), code = 201))
        val viewModel = newTask()
        viewModel.changeTitle("Pay rent")
        viewModel.changeDueKind(DueKind.DateTime)
        val at = ReminderDraft(ReminderTiming.At(LocalDateTime.of(2030, 1, 1, 9, 0)), repeatsUntilDismissed = true)
        viewModel.changeReminders(listOf(ReminderDraft(ReminderTiming.BeforeDue(10)), at))

        viewModel.changeDueKind(DueKind.None)
        assertEquals(listOf(at), viewModel.state.value.form.reminders)

        viewModel.leave()
        viewModel.closed()

        val reminder = server.takeRequest().json().getValue("reminders").jsonArray.single().jsonObject
        assertEquals("2030-01-01T09:00:00+06:30", reminder.getValue("remind_at").jsonPrimitive.content)
        assertTrue(reminder.getValue("repeat_until_dismissed").jsonPrimitive.boolean)
    }

    @Test
    fun `an edited Task opens with its reminders`() = runTest {
        server.enqueue(
            json(
                taskJson(
                    "t1",
                    dueAt = "2030-01-02T05:00:00+00:00",
                    extra = """"reminders":[${reminderJson("r1", minutesBefore = 60)},${reminderJson("r2", remindAt = "2030-01-01T05:00:00+00:00")}]""",
                ),
            ),
        )

        val state = editTask().loaded()

        assertEquals(
            listOf(ReminderTiming.BeforeDue(60), ReminderTiming.At(LocalDateTime.of(2030, 1, 1, 11, 30))),
            state.form.reminders.map { it.timing },
        )
    }

    @Test
    fun `a refused reminder shows under the reminders`() = runTest {
        server.enqueue(validationError("reminders.0.minutes_before" to "A reminder before the due needs a due."))
        val viewModel = newTask()
        viewModel.changeTitle("Call")
        viewModel.changeReminders(listOf(ReminderDraft(ReminderTiming.BeforeDue(10))))

        viewModel.leave()
        val state = viewModel.saved()

        assertEquals("A reminder before the due needs a due.", state.fieldErrors[TaskField.Reminders])
        assertNull(state.saveError)
    }
}
