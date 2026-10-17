package com.minthantzintun.trypost.feature.posts

import com.minthantzintun.trypost.core.data.PostChanges
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.model.MediaType
import com.minthantzintun.trypost.core.model.Platform
import com.minthantzintun.trypost.core.model.PlatformStatus
import com.minthantzintun.trypost.core.model.PostStatus
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.MainDispatcherRule
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.postJson
import com.minthantzintun.trypost.testing.postsPage
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.testApiFactory
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.testJson
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
import java.time.Instant

class PostsViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private val server = MockWebServer()
    private lateinit var store: FakeSessionStore
    private val changes = PostChanges()

    @Before
    fun setUp() {
        server.start()
        store = FakeSessionStore(server.session())
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun viewModel(): PostsViewModel = PostsViewModel(
        PostsRepository(testClient(store), changes),
        SessionRepository(store, testApiFactory(), testJson),
    )

    private suspend fun PostsViewModel.settled(tab: PostTab): TabState =
        state.first { it.tabs.getValue(tab).run { !isLoading && !isRefreshing && !isLoadingMore } }.tabs.getValue(tab)

    @Test
    fun `the scheduled tab loads first, with each post's accounts, media and times`() = runTest {
        server.enqueue(postsPage(listOf(postJson("p1", content = "Hello"))))

        val tab = viewModel().settled(PostTab.Scheduled)

        val request = server.takeRequest()
        assertEquals("/mobile/v1/posts", request.url.encodedPath)
        assertEquals("scheduled", request.url.queryParameter("status"))
        assertEquals("1", request.url.queryParameter("page"))
        assertEquals("Bearer good-token", request.headers["Authorization"])

        val post = tab.posts.single()
        assertEquals("Hello", post.content)
        assertEquals(PostStatus.Scheduled, post.status)
        assertEquals(Instant.parse("2030-01-02T09:30:00Z"), post.scheduledAt)
        assertEquals(MediaType.Video, post.media.single().type)
        val platform = post.platforms.single()
        assertEquals(Platform.Facebook, platform.platform)
        assertEquals(PlatformStatus.Pending, platform.status)
        assertEquals("My Page", platform.accountName)
        assertFalse(tab.hasMore)
    }

    @Test
    fun `each tab asks for its own posts once`() = runTest {
        server.enqueue(postsPage(listOf(postJson("s1"))))
        server.enqueue(postsPage(listOf(postJson("p1", status = "published"))))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)

        viewModel.selectTab(PostTab.Published)
        val published = viewModel.settled(PostTab.Published)
        viewModel.selectTab(PostTab.Scheduled)
        viewModel.selectTab(PostTab.Published)

        assertEquals(listOf("p1"), published.posts.map { it.id })
        assertEquals(2, server.requestCount)
        server.takeRequest()
        assertEquals("published", server.takeRequest().url.queryParameter("status"))
    }

    @Test
    fun `scrolling loads the next page and appends it`() = runTest {
        server.enqueue(postsPage(listOf(postJson("p1"), postJson("p2")), page = 1, lastPage = 2))
        server.enqueue(postsPage(listOf(postJson("p2"), postJson("p3")), page = 2, lastPage = 2))
        val viewModel = viewModel()
        assertTrue(viewModel.settled(PostTab.Scheduled).hasMore)

        viewModel.loadMore()
        val tab = viewModel.state.first { it.current.page == 2 && !it.current.isLoadingMore }.current

        assertEquals(listOf("p1", "p2", "p3"), tab.posts.map { it.id })
        assertFalse(tab.hasMore)
        server.takeRequest()
        assertEquals("2", server.takeRequest().url.queryParameter("page"))
    }

    @Test
    fun `pull to refresh replaces the loaded pages`() = runTest {
        server.enqueue(postsPage(listOf(postJson("old")), page = 1, lastPage = 3))
        server.enqueue(postsPage(listOf(postJson("new"))))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)

        viewModel.refresh()
        val tab = viewModel.state.first { it.current.posts.firstOrNull()?.id == "new" }.current

        assertEquals(listOf("new"), tab.posts.map { it.id })
        assertFalse(tab.isRefreshing)
        assertFalse(tab.hasMore)
    }

    @Test
    fun `a server error shows an error the owner can retry`() = runTest {
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        server.enqueue(postsPage(listOf(postJson("p1"))))
        val viewModel = viewModel()

        assertEquals(ApiError.Server(500), viewModel.settled(PostTab.Scheduled).error)

        viewModel.retry()
        val tab = viewModel.state.first { it.current.posts.isNotEmpty() }.current

        assertNull(tab.error)
        assertEquals(listOf("p1"), tab.posts.map { it.id })
    }

    @Test
    fun `a post changed on another screen reloads the loaded tabs`() = runTest {
        server.enqueue(postsPage(listOf(postJson("p1"))))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)

        server.enqueue(postsPage(emptyList()))
        viewModel.state.value.tabs.getValue(PostTab.Scheduled).posts.single().let(changes::announce)

        val tab = viewModel.state.first { it.tabs.getValue(PostTab.Scheduled).posts.isEmpty() }.tabs.getValue(PostTab.Scheduled)
        assertEquals(2, server.requestCount)
        assertTrue(tab.posts.isEmpty())
    }

    @Test
    fun `a 401 signs the app out`() = runTest {
        server.enqueue(json("""{"message":"Unauthorized."}""", code = 401))

        assertEquals(ApiError.Unauthorized, viewModel().settled(PostTab.Scheduled).error)
        assertNull(store.current())
    }

    @Test
    fun `signing out removes the session`() = runTest {
        server.enqueue(postsPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)

        viewModel.signOut()

        assertNull(store.session.first { it == null })
    }

    private val accountsJson = """{"data":[""" +
        """{"id":"a1","platform":"facebook","display_label":"My Page","status":"connected"},""" +
        """{"id":"a2","platform":"tiktok","display_label":"@me","status":"disconnected"}]}"""

    @Test
    fun `opening the account picker lists the social accounts, disconnected ones marked`() = runTest {
        server.enqueue(postsPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)
        server.enqueue(json(accountsJson))

        viewModel.loadAccounts()
        val accounts = viewModel.state.first { it.accounts.isNotEmpty() }.accounts

        server.takeRequest()
        assertEquals("/mobile/v1/social-accounts", server.takeRequest().url.encodedPath)
        assertEquals(listOf("a1", "a2"), accounts.map { it.id })
        assertEquals(Platform.TikTok, accounts[1].platform)
        assertEquals("@me", accounts[1].label)
        assertTrue(accounts[0].isConnected)
        assertFalse(accounts[1].isConnected)
    }

    @Test
    fun `choosing an account reloads the selected tab filtered to it`() = runTest {
        server.enqueue(postsPage(listOf(postJson("all"))))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)
        server.enqueue(postsPage(listOf(postJson("mine"))))

        viewModel.selectAccount("a1")
        val tab = viewModel.state.first { it.current.posts.firstOrNull()?.id == "mine" }.current

        assertEquals("a1", viewModel.state.value.selectedAccountId)
        assertEquals(listOf("mine"), tab.posts.map { it.id })
        assertNull(server.takeRequest().url.queryParameter("account"))
        assertEquals("a1", server.takeRequest().url.queryParameter("account"))
    }

    @Test
    fun `the chosen account filters every tab and the next pages`() = runTest {
        server.enqueue(postsPage(listOf(postJson("s0"))))
        server.enqueue(postsPage(listOf(postJson("s1")), page = 1, lastPage = 2))
        server.enqueue(postsPage(listOf(postJson("p1", status = "published"))))
        server.enqueue(postsPage(listOf(postJson("s2")), page = 2, lastPage = 2))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)

        viewModel.selectAccount("a1")
        viewModel.state.first { it.current.posts.firstOrNull()?.id == "s1" }
        viewModel.selectTab(PostTab.Published)
        viewModel.state.first { it.current.posts.firstOrNull()?.id == "p1" }
        viewModel.selectTab(PostTab.Scheduled)
        viewModel.loadMore()
        viewModel.state.first { it.current.page == 2 && !it.current.isLoadingMore }

        server.takeRequest()
        repeat(3) {
            assertEquals("a1", server.takeRequest().url.queryParameter("account"))
        }
        assertEquals("a1", viewModel.state.value.selectedAccountId)
    }

    @Test
    fun `choosing all accounts drops the filter`() = runTest {
        server.enqueue(postsPage(listOf(postJson("s0"))))
        server.enqueue(postsPage(listOf(postJson("mine"))))
        server.enqueue(postsPage(listOf(postJson("every"))))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)
        viewModel.selectAccount("a1")
        viewModel.state.first { it.current.posts.firstOrNull()?.id == "mine" }

        viewModel.selectAccount(null)
        viewModel.state.first { it.current.posts.firstOrNull()?.id == "every" }

        assertNull(viewModel.state.value.selectedAccountId)
        server.takeRequest()
        server.takeRequest()
        assertNull(server.takeRequest().url.queryParameter("account"))
    }

    @Test
    fun `accounts that fail to load show an error and keep the list unfiltered`() = runTest {
        server.enqueue(postsPage(emptyList()))
        val viewModel = viewModel()
        viewModel.settled(PostTab.Scheduled)
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))

        viewModel.loadAccounts()
        val state = viewModel.state.first { it.accountsError != null }

        assertEquals(ApiError.Server(500), state.accountsError)
        assertTrue(state.accounts.isEmpty())
        assertNull(state.selectedAccountId)
    }
}
