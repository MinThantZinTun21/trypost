package com.minthantzintun.trypost.core.timer

import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.noContent
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.testClient
import com.minthantzintun.trypost.testing.timerSessionJson
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test
import java.time.Clock
import java.time.Instant
import java.time.ZoneOffset

class TimerTrackerTest {
    private val server = MockWebServer()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Unconfined)
    private val clock = Clock.fixed(Instant.parse("2030-01-01T03:50:00Z"), ZoneOffset.UTC)
    private var launches = 0
    private lateinit var repository: TasksRepository
    private lateinit var tracker: TimerTracker

    @Before
    fun setUp() {
        server.start()
        repository = TasksRepository(testClient(FakeSessionStore(server.session())))
        tracker = TimerTracker({ launches++ }, repository, scope, clock)
    }

    @After
    fun tearDown() {
        scope.cancel()
        server.close()
    }

    private suspend fun running(taskId: String = "t1") {
        server.enqueue(json("""{"data":${timerSessionJson("s1", taskId = taskId)}}"""))
        tracker.refresh()
    }

    @Test
    fun `a running timer from the server shows and starts the service`() = runTest {
        running()

        assertEquals("s1", tracker.running.value?.id)
        assertEquals(1, launches)
    }

    @Test
    fun `a Pomodoro whose time is up is not shown as running`() = runTest {
        server.enqueue(json("""{"data":${timerSessionJson("s1", mode = "pomodoro", plannedSeconds = 1500)}}"""))

        tracker.refresh()

        assertNull(tracker.running.value)
        assertEquals(0, launches)
    }

    @Test
    fun `marking its Task Done ends the shown timer`() = runTest {
        running()
        server.enqueue(json(taskJson("t1", status = "done")))

        repository.setStatus("t1", TaskStatus.Done)

        assertNull(tracker.running.value)
    }

    @Test
    fun `another Task done or deleted leaves it running`() = runTest {
        running()
        server.enqueue(json(taskJson("t2", status = "done")))
        server.enqueue(noContent())

        repository.setStatus("t2", TaskStatus.Done)
        repository.delete("t2")

        assertEquals("s1", tracker.running.value?.id)
    }

    @Test
    fun `deleting its Task ends the shown timer`() = runTest {
        running()
        server.enqueue(noContent())

        repository.delete("t1")

        assertNull(tracker.running.value)
    }
}
