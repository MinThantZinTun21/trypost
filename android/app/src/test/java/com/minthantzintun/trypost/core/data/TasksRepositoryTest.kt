package com.minthantzintun.trypost.core.data

import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TaskDraft
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.tagGroupJson
import com.minthantzintun.trypost.testing.tagGroupsList
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.taskTagRefJson
import com.minthantzintun.trypost.testing.tasksPage
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
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
}
