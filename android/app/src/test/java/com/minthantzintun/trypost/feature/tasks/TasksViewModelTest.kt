package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.settings.TaskLayout
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.FakeSettingsStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.tasksPage
import com.minthantzintun.trypost.testing.testClient
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class TasksViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var repository: TasksRepository
    private val settingsStore = FakeSettingsStore()

    @Before
    fun setUp() {
        server.start()
        repository = TasksRepository(testClient(FakeSessionStore(server.session())))
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): TasksViewModel = TasksViewModel(repository, settingsStore)

    /** Waits until [tab] has loaded [ids] (or failed), so a reset in between never passes. */
    private suspend fun TasksViewModel.loaded(tab: TaskTab, vararg ids: String): TaskTabState =
        state.first { state ->
            state.tabs.getValue(tab).run { !isLoading && !isRefreshing && !isLoadingMore && tasks.map { it.id } == ids.toList() }
        }.tabs.getValue(tab)

    @Test
    fun `the Open tab loads first`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1", dueAt = "2030-01-02T10:00:00+00:00")), lastPage = 1))

        val tab = viewModel().loaded(TaskTab.Open, "t1")

        val request = server.takeRequest()
        assertEquals("/mobile/v1/tasks", request.url.encodedPath)
        assertEquals("open", request.url.queryParameter("tab"))
        assertEquals("1", request.url.queryParameter("page"))
        assertNull(request.url.queryParameter("q"))
        assertFalse(tab.hasMore)
    }

    @Test
    fun `the Done tab loads once when first opened`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("open"))))
        server.enqueue(tasksPage(listOf(taskJson("done", status = "done"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "open")

        viewModel.selectTab(TaskTab.Done)
        viewModel.loaded(TaskTab.Done, "done")
        viewModel.selectTab(TaskTab.Open)
        viewModel.selectTab(TaskTab.Done)

        assertEquals(2, server.requestCount)
        server.takeRequest()
        assertEquals("done", server.takeRequest().url.queryParameter("tab"))
    }

    @Test
    fun `scrolling loads the next page and appends it`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"), taskJson("t2")), page = 1, lastPage = 2))
        server.enqueue(tasksPage(listOf(taskJson("t2"), taskJson("t3")), page = 2, lastPage = 2))
        val viewModel = viewModel()
        assertTrue(viewModel.loaded(TaskTab.Open, "t1", "t2").hasMore)

        viewModel.loadMore()
        val tab = viewModel.loaded(TaskTab.Open, "t1", "t2", "t3")

        assertFalse(tab.hasMore)
        server.takeRequest()
        assertEquals("2", server.takeRequest().url.queryParameter("page"))
    }

    @Test
    fun `a failed next page keeps the loaded Tasks and can be retried`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1")), page = 1, lastPage = 2))
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")

        viewModel.loadMore()
        val tab = viewModel.state.first { it.current.loadMoreError != null }.current

        assertEquals(listOf("t1"), tab.tasks.map { it.id })
        assertTrue(tab.loadMoreError is ApiError.Server)
    }

    @Test
    fun `filtering by Task tags reloads every tab from the first page`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1")), page = 1, lastPage = 2))
        server.enqueue(tasksPage(listOf(taskJson("tagged"))))
        server.enqueue(tasksPage(listOf(taskJson("done-tagged", status = "done"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")

        viewModel.filterByTaskTags(listOf("k1", "k2"))
        viewModel.loaded(TaskTab.Open, "tagged")
        viewModel.selectTab(TaskTab.Done)
        viewModel.loaded(TaskTab.Done, "done-tagged")

        server.takeRequest()
        val filtered = server.takeRequest()
        assertEquals("1", filtered.url.queryParameter("page"))
        assertEquals(listOf("k1", "k2"), filtered.url.queryParameterValues("task_tags[]"))
        assertEquals(listOf("k1", "k2"), server.takeRequest().url.queryParameterValues("task_tags[]"))
        assertTrue(viewModel.state.value.isFiltered)
    }

    @Test
    fun `removing one Task tag filter keeps the others`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"))))
        server.enqueue(tasksPage(listOf(taskJson("both"))))
        server.enqueue(tasksPage(listOf(taskJson("one"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")
        viewModel.filterByTaskTags(listOf("k1", "k2"))
        viewModel.loaded(TaskTab.Open, "both")

        viewModel.removeTaskTagFilter("k1")
        viewModel.loaded(TaskTab.Open, "one")

        repeat(2) { server.takeRequest() }
        assertEquals(listOf("k2"), server.takeRequest().url.queryParameterValues("task_tags[]"))
        assertEquals(listOf("k2"), viewModel.state.value.taskTagIds)
    }

    @Test
    fun `search waits for typing to pause, then asks once`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"))))
        server.enqueue(tasksPage(listOf(taskJson("found"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")

        viewModel.changeQuery("sup")
        viewModel.changeQuery("supplier ")
        viewModel.loaded(TaskTab.Open, "found")

        assertEquals(2, server.requestCount)
        server.takeRequest()
        assertEquals("supplier", server.takeRequest().url.queryParameter("q"))
        assertEquals("supplier ", viewModel.state.value.query)
    }

    @Test
    fun `the search key searches at once and clearing filters drops the search and the Task tags`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"))))
        server.enqueue(tasksPage(listOf(taskJson("found"))))
        server.enqueue(tasksPage(listOf(taskJson("found-tagged"))))
        server.enqueue(tasksPage(listOf(taskJson("all"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")
        viewModel.changeQuery("supplier")
        viewModel.search()
        viewModel.loaded(TaskTab.Open, "found")
        viewModel.filterByTaskTags(listOf("k1"))
        viewModel.loaded(TaskTab.Open, "found-tagged")

        viewModel.clearFilters()
        viewModel.loaded(TaskTab.Open, "all")

        server.takeRequest()
        assertEquals("supplier", server.takeRequest().url.queryParameter("q"))
        val both = server.takeRequest()
        assertEquals("supplier", both.url.queryParameter("q"))
        assertEquals(listOf("k1"), both.url.queryParameterValues("task_tags[]"))
        val cleared = server.takeRequest()
        assertNull(cleared.url.queryParameter("q"))
        assertTrue(cleared.url.queryParameterValues("task_tags[]").isEmpty())
        assertFalse(viewModel.state.value.isFiltered)
        assertEquals("", viewModel.state.value.query)
        assertEquals(4, server.requestCount)
    }

    @Test
    fun `a filter on a deleted Task tag is dropped`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"))))
        server.enqueue(tasksPage(listOf(taskJson("both"))))
        server.enqueue(tasksPage(listOf(taskJson("kept"))))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")
        viewModel.filterByTaskTags(listOf("k1", "gone"))
        viewModel.loaded(TaskTab.Open, "both")

        viewModel.keepOnlyKnownTaskTags(setOf("k1", "k3"))
        viewModel.loaded(TaskTab.Open, "kept")
        viewModel.keepOnlyKnownTaskTags(setOf("k1"))

        assertEquals(listOf("k1"), viewModel.state.value.taskTagIds)
        assertEquals(3, server.requestCount)
    }

    @Test
    fun `a Task changed on another screen reloads the loaded tabs`() = runTest {
        server.enqueue(tasksPage(listOf(taskJson("t1"))))
        server.enqueue(json(taskJson("t1", status = "done")))
        server.enqueue(tasksPage(emptyList()))
        val viewModel = viewModel()
        viewModel.loaded(TaskTab.Open, "t1")

        repository.setStatus("t1", TaskStatus.Done)
        viewModel.loaded(TaskTab.Open)

        server.takeRequest()
        assertEquals("/mobile/v1/tasks/t1/status", server.takeRequest().url.encodedPath)
        assertEquals("open", server.takeRequest().url.queryParameter("tab"))
    }

    @Test
    fun `a failed first page shows the error`() = runTest {
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))

        val tab = viewModel().state.first { it.current.error != null }.current

        assertTrue(tab.error is ApiError.Server)
        assertTrue(tab.tasks.isEmpty())
    }

    @Test
    fun `the layout follows the stored choice and the toggle keeps the other one`() = runTest {
        server.enqueue(tasksPage(emptyList()))
        val viewModel = viewModel()

        assertEquals(TaskLayout.Grid, viewModel.layout.first())

        viewModel.toggleLayout()

        assertEquals(TaskLayout.List, viewModel.layout.first { it == TaskLayout.List })
        assertEquals(TaskLayout.List, settingsStore.taskLayout.value)
    }
}
