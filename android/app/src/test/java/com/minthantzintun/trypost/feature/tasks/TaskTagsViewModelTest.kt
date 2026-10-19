package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.TaskTag
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.tagGroupJson
import com.minthantzintun.trypost.testing.tagGroupsList
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
import com.minthantzintun.trypost.testing.validationError
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
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

class TaskTagsViewModelTest {
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

    private fun viewModel(): TaskTagsViewModel = TaskTagsViewModel(repository)

    private suspend fun TaskTagsViewModel.listed(vararg groupIds: String): TaskTagsUiState =
        state.first { state -> !state.isLoading && state.tagGroups?.map { it.id } == groupIds.toList() }

    @Test
    fun `a new Tag group is saved, the dialog closes and the list loads again`() = runTest {
        server.enqueue(tagGroupsList(emptyList()))
        server.enqueue(json(tagGroupJson("g1", name = "Context", color = "teal"), code = 201))
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", name = "Context", color = "teal"))))
        val viewModel = viewModel()
        viewModel.listed()

        viewModel.openDialog(TagDialog.NewTagGroup)
        viewModel.saveTagGroup("Context", TagColor.Teal)
        val state = viewModel.listed("g1")

        assertNull(state.dialog)
        assertEquals(TagColor.Teal, state.tagGroups!!.single().color)
        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/tag-groups", request.url.encodedPath)
        assertEquals("teal", testJson.parseToJsonElement(request.body!!.utf8()).jsonObject.getValue("color").jsonPrimitive.content)
    }

    @Test
    fun `a refused name stays in the dialog under the name`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1"))))
        server.enqueue(validationError("name" to "The name has already been taken."))
        val viewModel = viewModel()
        val group = viewModel.listed("g1").tagGroups!!.single()

        viewModel.openDialog(TagDialog.NewTaskTag(group))
        viewModel.saveTaskTag("Home")
        val state = viewModel.state.first { it.nameError != null }

        assertEquals("The name has already been taken.", state.nameError)
        assertEquals(TagDialog.NewTaskTag(group), state.dialog)
        assertNull(state.dialogError)
        assertEquals("/mobile/v1/tag-groups/g1/task-tags", server.run { takeRequest(); takeRequest() }.url.encodedPath)
    }

    @Test
    fun `renaming a Task tag and recolouring a Tag group hit their own routes`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", taskTagIds = listOf("k1")))))
        server.enqueue(json("""{"id":"k1","name":"House","tag_group_id":"g1"}"""))
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", taskTagIds = listOf("k1")))))
        server.enqueue(json(tagGroupJson("g1", color = "brown")))
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", color = "brown"))))
        val viewModel = viewModel()
        val group = viewModel.listed("g1").tagGroups!!.single()

        viewModel.openDialog(TagDialog.EditTaskTag(TaskTag("k1", "Home", "g1")))
        viewModel.saveTaskTag("House")
        // Wait for the rename's reload too, or it races the recolour for the queued responses.
        viewModel.state.first { it.dialog == null && !it.isSaving && !it.isLoading && server.requestCount >= 3 }
        viewModel.openDialog(TagDialog.EditTagGroup(group))
        viewModel.saveTagGroup(group.name, TagColor.Brown)
        viewModel.state.first { state -> state.dialog == null && state.tagGroups?.single()?.color == TagColor.Brown }

        server.takeRequest()
        val renamed = server.takeRequest()
        assertEquals("PUT /mobile/v1/task-tags/k1", "${renamed.method} ${renamed.url.encodedPath}")
        server.takeRequest()
        val recoloured = server.takeRequest()
        assertEquals("PUT /mobile/v1/tag-groups/g1", "${recoloured.method} ${recoloured.url.encodedPath}")
    }

    @Test
    fun `deleting a Tag group after the confirm loads the list again`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1"), tagGroupJson("g2"))))
        server.enqueue(noContent())
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g2"))))
        val viewModel = viewModel()
        val group = viewModel.listed("g1", "g2").tagGroups!!.first()

        viewModel.openDialog(TagDialog.DeleteTagGroup(group))
        viewModel.confirmDelete()
        val state = viewModel.listed("g2")

        assertNull(state.dialog)
        server.takeRequest()
        val request = server.takeRequest()
        assertEquals("DELETE /mobile/v1/tag-groups/g1", "${request.method} ${request.url.encodedPath}")
    }

    @Test
    fun `closing the sheet while a Task tag saves keeps its page until the save ends`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1"))))
        server.enqueue(json("""{"id":"k1","name":"Home","tag_group_id":"g1"}""", code = 201))
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", taskTagIds = listOf("k1")))))
        val viewModel = viewModel()
        val group = viewModel.listed("g1").tagGroups!!.single()

        viewModel.openDialog(TagDialog.NewTaskTag(group))
        viewModel.saveTaskTag("Home")
        viewModel.closeDialog()

        assertEquals(TagDialog.NewTaskTag(group), viewModel.state.value.dialog)
        assertNull(viewModel.state.first { !it.isSaving }.dialog)
        server.takeRequest()
        val body = testJson.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        assertEquals("Home", body.getValue("name").jsonPrimitive.content)
    }

    @Test
    fun `a failed delete keeps the confirm open with the reason`() = runTest {
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1", taskTagIds = listOf("k1")))))
        server.enqueue(json("""{"message":"Not Found"}""", code = 404))
        val viewModel = viewModel()
        viewModel.listed("g1")
        val dialog = TagDialog.DeleteTaskTag(TaskTag("k1", "Tag k1", "g1"))

        viewModel.openDialog(dialog)
        viewModel.confirmDelete()
        val state = viewModel.state.first { it.dialogError != null }

        assertEquals(dialog, state.dialog)
        assertEquals(ApiError.NotFound, state.dialogError)

        viewModel.closeDialog()
        assertNull(viewModel.state.value.dialog)
        assertNull(viewModel.state.value.dialogError)
    }

    @Test
    fun `a failed load keeps no list and can be retried`() = runTest {
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        server.enqueue(tagGroupsList(listOf(tagGroupJson("g1"))))
        val viewModel = viewModel()

        val failed = viewModel.state.first { it.loadError != null }
        assertNull(failed.tagGroups)
        assertTrue(failed.loadError is ApiError.Server)

        viewModel.load()

        viewModel.listed("g1")
    }
}
