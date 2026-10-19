package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.AttachmentType
import com.minthantzintun.trypost.core.model.ReminderDraft
import com.minthantzintun.trypost.core.model.ReminderTiming
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TaskDraft
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.model.TimerMode
import com.minthantzintun.trypost.core.model.TimerOutcome
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.reminderJson
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.tagGroupJson
import com.minthantzintun.trypost.testing.tagGroupsList
import com.minthantzintun.trypost.testing.taskAttachmentJson
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.taskTagRefJson
import com.minthantzintun.trypost.testing.tasksPage
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import com.minthantzintun.trypost.testing.timerSessionJson
import com.minthantzintun.trypost.testing.validationError
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.boolean
import kotlinx.serialization.json.int
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
import org.junit.Test
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.ZoneId
import java.time.ZoneOffset

class TasksRepositoryTest {
    private val server = MockWebServer()
    private val yangon = ZoneId.of("Asia/Yangon")

    @Before
    fun setUp() {
        server.start()
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun repository() = TasksRepository(testClient(FakeSessionStore(server.session())))

    private fun RecordedRequest.jsonBody(): JsonObject = testJson.parseToJsonElement(body!!.utf8()).jsonObject

    private fun draft(due: TaskDue? = null, description: String? = null, taskTagIds: List<String> = emptyList()) = TaskDraft(
        title = "  Call the supplier  ",
        description = description,
        priority = TaskPriority.High,
        due = due,
        taskTagIds = taskTagIds,
    )

    @Test
    fun `a page asks for its tab, page, search and every Task tag`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1")), page = 2, lastPage = 3))

        val result = repository().page(TaskTab.Open, 2, taskTagIds = listOf("a", "b"), query = " supplier ")

        val request = server.takeRequest()
        assertEquals("GET", request.method)
        assertEquals("/mobile/v1/tasks", request.url.encodedPath)
        assertEquals("open", request.url.queryParameter("tab"))
        assertEquals("2", request.url.queryParameter("page"))
        assertEquals("supplier", request.url.queryParameter("q"))
        assertEquals(listOf("a", "b"), request.url.queryParameterValues("task_tags[]"))
        val page = (result as ApiResult.Success).value
        assertEquals(listOf("t1"), page.tasks.map { it.id })
        assertEquals(2, page.page)
        assertEquals(3, page.lastPage)
    }

    @Test
    fun `a page without filters sends no search and no Task tags`() = runTest {
        server.enqueue(tasksPage(emptyList()))

        repository().page(TaskTab.Done, 1, query = "   ")

        val request = server.takeRequest()
        assertEquals("done", request.url.queryParameter("tab"))
        assertNull(request.url.queryParameter("q"))
        assertTrue(request.url.queryParameterValues("task_tags[]").isEmpty())
    }

    @Test
    fun `a Task reads its due, priority, status and Task tags`() = runTest {
        server.enqueue(
            json(
                taskJson(
                    id = "t1",
                    status = "in_progress",
                    priority = 2,
                    dueAt = "2030-01-01T17:30:00+00:00",
                    dueAllDay = true,
                    description = "Ask about the price",
                    taskTags = listOf(taskTagRefJson("k1", name = "Office", color = "teal")),
                ),
            ),
        )

        val task = (repository().task("t1") as ApiResult.Success).value

        assertEquals("/mobile/v1/tasks/t1", server.takeRequest().url.encodedPath)
        assertEquals(TaskStatus.InProgress, task.status)
        assertEquals(TaskPriority.Medium, task.priority)
        assertEquals(Instant.parse("2030-01-01T17:30:00Z"), task.dueAt)
        assertTrue(task.isDueAllDay)
        assertEquals("Ask about the price", task.description)
        assertEquals("Office", task.taskTags.single().name)
        assertEquals("Context", task.taskTags.single().tagGroupName)
        assertEquals(TagColor.Teal, task.taskTags.single().color)
    }

    @Test
    fun `a Task without a title reads its colour and pin`() = runTest {
        server.enqueue(json(taskJson("t1", title = null, description = "- [ ] Milk", color = "yellow", pinned = true)))

        val task = (repository().task("t1") as ApiResult.Success).value

        assertNull(task.title)
        assertEquals(TagColor.Yellow, task.color)
        assertTrue(task.isPinned)
    }

    @Test
    fun `a blank title is left out, and the colour and pin are sent`() = runTest {
        server.enqueue(json(taskJson("t1", title = null, description = "Body"), code = 201))

        repository().create(draft(description = "Body").copy(title = "   ", color = TagColor.Blue, isPinned = true), yangon)

        val body = server.takeRequest().jsonBody()
        // The wire leaves nulls out; the server reads a missing title as none.
        assertTrue("title" !in body)
        assertEquals("blue", body.getValue("color").jsonPrimitive.content)
        assertTrue(body.getValue("pinned").jsonPrimitive.boolean)
    }

    @Test
    fun `create sends a timed due with the phone's UTC offset`() = runTest {
        server.enqueue(json(taskJson("t1"), code = 201))

        val result = repository().create(
            draft(due = TaskDue.Timed(LocalDateTime.of(2030, 1, 1, 15, 0)), description = "Price", taskTagIds = listOf("k1", "k2")),
            yangon,
        )

        assertTrue(result is ApiResult.Success)
        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/tasks", request.url.encodedPath)
        val body = request.jsonBody()
        assertEquals("Call the supplier", body.getValue("title").jsonPrimitive.content)
        assertEquals("Price", body.getValue("description").jsonPrimitive.content)
        assertEquals(3, body.getValue("priority").jsonPrimitive.int)
        assertEquals("2030-01-01T15:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertFalse(body.getValue("due_all_day").jsonPrimitive.boolean)
        assertEquals(listOf("k1", "k2"), body.getValue("task_tags").jsonArray.map { it.jsonPrimitive.content })
    }

    @Test
    fun `an all-day due is the start of that day in the phone's timezone`() = runTest {
        server.enqueue(json(taskJson("t1")))

        repository().update("t1", draft(due = TaskDue.AllDay(LocalDate.of(2030, 1, 1))), yangon)

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/tasks/t1", request.url.encodedPath)
        val body = request.jsonBody()
        assertEquals("2030-01-01T00:00:00+06:30", body.getValue("due_at").jsonPrimitive.content)
        assertTrue(body.getValue("due_all_day").jsonPrimitive.boolean)
    }

    @Test
    fun `a UTC offset is written as +00 00, never Z`() {
        assertEquals("2030-01-01T09:00:00+00:00", dueAtWire(TaskDue.Timed(LocalDateTime.of(2030, 1, 1, 9, 0)), ZoneOffset.UTC))
    }

    @Test
    fun `a save without due or description leaves both out and clears the Task tags`() = runTest {
        server.enqueue(json(taskJson("t1")))

        repository().update("t1", draft(description = "  "), yangon)

        val body = server.takeRequest().jsonBody()
        assertFalse(body.containsKey("due_at"))
        assertFalse(body.containsKey("description"))
        assertFalse(body.getValue("due_all_day").jsonPrimitive.boolean)
        assertTrue(body.getValue("task_tags").jsonArray.isEmpty())
    }

    @Test
    fun `a status change goes to the status endpoint`() = runTest {
        val repository = repository()
        server.enqueue(json(taskJson("t1", status = "done")))

        val result = repository.setStatus("t1", TaskStatus.Done)

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/mobile/v1/tasks/t1/status", request.url.encodedPath)
        assertEquals("done", request.jsonBody().getValue("status").jsonPrimitive.content)
        assertEquals(TaskStatus.Done, (result as ApiResult.Success).value.status)
    }

    @Test
    fun `delete answers success on 204`() = runTest {
        server.enqueue(noContent())

        val result = repository().delete("t1")

        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/mobile/v1/tasks/t1", request.url.encodedPath)
        assertTrue(result is ApiResult.Success)
    }

    @Test
    fun `a refused save names its fields`() = runTest {
        server.enqueue(validationError("due_at" to "The due must have an offset.", "task_tags.0" to "Unknown tag."))

        val result = repository().create(draft(), yangon)

        val error = (result as ApiResult.Failure).error as ApiError.Validation
        assertEquals(setOf("due_at", "task_tags.0"), error.fields.keys)
    }

    @Test
    fun `Tag groups list with their Task tags and colours`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", name = "Context", color = "red", taskTagIds = listOf("k1", "k2")))))

        val groups = (repository().tagGroups() as ApiResult.Success).value

        assertEquals("/mobile/v1/tag-groups", server.takeRequest().url.encodedPath)
        assertEquals("Context", groups.single().name)
        assertEquals(TagColor.Red, groups.single().color)
        assertEquals(listOf("k1", "k2"), groups.single().taskTags.map { it.id })
    }

    @Test
    fun `Tag group and Task tag changes hit their routes with their bodies`() = runTest {
        val repository = repository()
        server.enqueue(json(tagGroupJson("g1"), code = 201))
        server.enqueue(json(tagGroupJson("g1")))
        server.enqueue(noContent())
        server.enqueue(json("""{"id":"k1","name":"Home","tag_group_id":"g1"}""", code = 201))
        server.enqueue(json("""{"id":"k1","name":"House","tag_group_id":"g1"}"""))
        server.enqueue(noContent())

        repository.createTagGroup(" Context ", TagColor.Teal)
        repository.updateTagGroup("g1", "Area", TagColor.Brown)
        repository.deleteTagGroup("g1")
        repository.createTaskTag("g1", "Home")
        repository.renameTaskTag("k1", "House")
        repository.deleteTaskTag("k1")

        val created = server.takeRequest()
        assertEquals("POST /mobile/v1/tag-groups", "${created.method} ${created.url.encodedPath}")
        assertEquals("Context", created.jsonBody().getValue("name").jsonPrimitive.content)
        assertEquals("teal", created.jsonBody().getValue("color").jsonPrimitive.content)
        val updated = server.takeRequest()
        assertEquals("PUT /mobile/v1/tag-groups/g1", "${updated.method} ${updated.url.encodedPath}")
        assertEquals("brown", updated.jsonBody().getValue("color").jsonPrimitive.content)
        val deleted = server.takeRequest()
        assertEquals("DELETE /mobile/v1/tag-groups/g1", "${deleted.method} ${deleted.url.encodedPath}")
        val tagCreated = server.takeRequest()
        assertEquals("POST /mobile/v1/tag-groups/g1/task-tags", "${tagCreated.method} ${tagCreated.url.encodedPath}")
        assertEquals("Home", tagCreated.jsonBody().getValue("name").jsonPrimitive.content)
        val renamed = server.takeRequest()
        assertEquals("PUT /mobile/v1/task-tags/k1", "${renamed.method} ${renamed.url.encodedPath}")
        assertEquals("House", renamed.jsonBody().getValue("name").jsonPrimitive.content)
        val tagDeleted = server.takeRequest()
        assertEquals("DELETE /mobile/v1/task-tags/k1", "${tagDeleted.method} ${tagDeleted.url.encodedPath}")
    }

    @Test
    fun `a Task reads its reminders, Timer sessions and attachments`() = runTest {
        server.enqueue(
            json(
                taskJson(
                    "t1",
                    extra = """"reminders":[${reminderJson("r1", minutesBefore = 10, repeats = true)},${reminderJson("r2", dismissedAt = "2030-01-01T05:00:00+00:00")}],""" +
                        """"tracked_seconds":1500,"timer_sessions":[${timerSessionJson("s1", mode = "pomodoro", plannedSeconds = 1500, endedAt = "2030-01-01T03:25:00+00:00", outcome = "completed")}],""" +
                        """"attachments":[${taskAttachmentJson("m1", type = "audio", mimeType = "audio/mp4", fileName = "note.m4a")}]""",
                ),
            ),
        )

        val task = (repository().task("t1") as ApiResult.Success).value

        assertEquals(listOf("r1", "r2"), task.reminders.map { it.id })
        assertEquals(10, task.reminders.first().minutesBefore)
        assertTrue(task.reminders.first().repeatsUntilDismissed)
        assertEquals(listOf("r1"), task.activeReminders.map { it.id })
        assertEquals(TimerMode.Pomodoro, task.timerSessions.single().mode)
        assertEquals(TimerOutcome.Completed, task.timerSessions.single().outcome)
        assertEquals(AttachmentType.Audio, task.attachments.single().type)
        assertEquals("note.m4a", task.attachments.single().fileName)
    }

    @Test
    fun `a save sends the whole set of reminders, a set time with the phone's UTC offset`() = runTest {
        server.enqueue(json(taskJson("t1")))

        repository().update(
            "t1",
            draft().copy(
                reminders = listOf(
                    ReminderDraft(ReminderTiming.At(LocalDateTime.of(2030, 1, 1, 9, 0)), repeatsUntilDismissed = true),
                    ReminderDraft(ReminderTiming.BeforeDue(60)),
                ),
            ),
            yangon,
        )

        val reminders = server.takeRequest().jsonBody().getValue("reminders").jsonArray.map { it.jsonObject }
        assertEquals("at", reminders[0].getValue("type").jsonPrimitive.content)
        assertEquals("2030-01-01T09:00:00+06:30", reminders[0].getValue("remind_at").jsonPrimitive.content)
        assertTrue(reminders[0].getValue("repeat_until_dismissed").jsonPrimitive.boolean)
        assertEquals("before_due", reminders[1].getValue("type").jsonPrimitive.content)
        assertEquals(60, reminders[1].getValue("minutes_before").jsonPrimitive.int)
        assertFalse(reminders[1].getValue("repeat_until_dismissed").jsonPrimitive.boolean)
    }

    @Test
    fun `a save that keeps the stored reminders leaves them out`() = runTest {
        server.enqueue(json(taskJson("t1")))

        repository().update("t1", draft(), yangon)

        assertFalse(server.takeRequest().jsonBody().containsKey("reminders"))
    }

    @Test
    fun `the upcoming reminders carry the Task's title for the notification`() = runTest {
        server.enqueue(json("""{"data":[${reminderJson("r1", taskId = "t2", repeats = true, taskTitle = "Call the bank")}]}"""))

        val reminder = (repository().upcomingReminders() as ApiResult.Success).value.single()

        assertEquals("t2", reminder.taskId)
        assertEquals(Instant.parse("2030-01-01T05:00:00Z").toEpochMilli(), reminder.remindAtMillis)
        assertTrue(reminder.repeatsUntilDismissed)
        assertEquals("Call the bank", reminder.taskLabel)
    }

    @Test
    fun `a Pomodoro starts with its length and a free timer without one`() = runTest {
        val repository = repository()
        server.enqueue(json(timerSessionJson("s1", mode = "pomodoro", plannedSeconds = 1500), code = 201))
        server.enqueue(json(timerSessionJson("s2"), code = 201))

        val pomodoro = repository.startTimer("t1", TimerMode.Pomodoro, plannedSeconds = 1500)
        repository.startTimer("t1", TimerMode.Free, plannedSeconds = 1500)

        val first = server.takeRequest()
        assertEquals("/mobile/v1/tasks/t1/timer", first.url.encodedPath)
        assertEquals("pomodoro", first.jsonBody().getValue("mode").jsonPrimitive.content)
        assertEquals(1500, first.jsonBody().getValue("planned_seconds").jsonPrimitive.int)
        assertEquals(Instant.parse("2030-01-01T03:25:00Z"), (pomodoro as ApiResult.Success).value?.plannedEndAt)
        val second = server.takeRequest().jsonBody()
        assertEquals("free", second.getValue("mode").jsonPrimitive.content)
        assertTrue("planned_seconds" !in second)
    }

    @Test
    fun `a timer already running elsewhere is a conflict`() = runTest {
        server.enqueue(json("""{"message":"A timer is already running."}""", code = 409))

        val result = repository().startTimer("t1", TimerMode.Free, plannedSeconds = null)

        assertTrue(result is ApiResult.Failure)
    }
}
