package com.minthantzintun.trypost.core.reminders

import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.testing.FakeSessionStore
import com.minthantzintun.trypost.testing.json
import com.minthantzintun.trypost.testing.reminderJson
import com.minthantzintun.trypost.testing.session
import com.minthantzintun.trypost.testing.taskJson
import com.minthantzintun.trypost.testing.testClient
import kotlinx.coroutines.test.runTest
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.time.Clock
import java.time.Instant
import java.time.ZoneId
import java.time.ZoneOffset
import java.util.concurrent.TimeUnit

class ReminderAlarmsTest {
    private val server = MockWebServer()
    private val store = MemoryReminderStore()
    private val scheduler = RecordingScheduler()
    private val notifier = RecordingNotifier()
    private var now = Instant.parse("2030-01-01T04:00:00Z")
    private lateinit var alarms: ReminderAlarms

    @Before
    fun setUp() {
        server.start()
        val clock = object : Clock() {
            override fun getZone() = ZoneOffset.UTC

            override fun withZone(zone: ZoneId?) = this

            override fun instant(): Instant = now
        }
        alarms = ReminderAlarms(TasksRepository(testClient(FakeSessionStore(server.session()))), store, scheduler, notifier, clock)
    }

    @After
    fun tearDown() {
        server.close()
    }

    private fun upcoming(vararg reminders: String): MockResponse = json("""{"data":[${reminders.joinToString(",")}]}""")

    @Test
    fun `a sync keeps the upcoming list and arms an alarm for each reminder`() = runTest {
        server.enqueue(
            upcoming(
                reminderJson("r1", remindAt = "2030-01-01T05:00:00+00:00", taskTitle = "Call the bank"),
                reminderJson("r2", taskId = "t2", remindAt = "2030-01-01T06:30:00+00:00", repeats = true),
            ),
        )

        alarms.sync()

        assertEquals("/mobile/v1/reminders/upcoming", server.takeRequest().url.encodedPath)
        assertEquals(
            mapOf("r1" to Instant.parse("2030-01-01T05:00:00Z"), "r2" to Instant.parse("2030-01-01T06:30:00Z")),
            scheduler.armed,
        )
        assertEquals("Call the bank", store.state.upcoming.first { it.id == "r1" }.taskLabel)
        assertEquals(setOf("r1", "r2"), store.state.armedIds)
    }

    @Test
    fun `a reminder gone from the server loses its alarm and notification`() = runTest {
        server.enqueue(upcoming(reminderJson("r1"), reminderJson("r2", remindAt = "2030-01-01T06:00:00+00:00")))
        server.enqueue(upcoming(reminderJson("r2", remindAt = "2030-01-01T06:00:00+00:00")))
        alarms.sync()

        alarms.sync()

        assertEquals(setOf("r2"), scheduler.armed.keys)
        assertTrue("r1" in notifier.cancelled)
    }

    @Test
    fun `with no connection the stored list is re-armed`() = runTest {
        server.enqueue(upcoming(reminderJson("r1")))
        alarms.sync()
        scheduler.armed.clear()
        server.close()

        alarms.sync()

        assertEquals(setOf("r1"), scheduler.armed.keys)
    }

    @Test
    fun `a ring shows the notification, and a repeating one is armed again in five minutes`() = runTest {
        server.enqueue(upcoming(reminderJson("r1", remindAt = "2030-01-01T04:00:00+00:00", repeats = true)))
        alarms.sync()

        alarms.ring("r1")

        assertEquals(listOf("r1"), notifier.shown)
        assertEquals(Instant.parse("2030-01-01T04:05:00Z"), scheduler.armed["r1"])
    }

    @Test
    fun `a ring that does not repeat leaves the notification but sets no alarm`() = runTest {
        server.enqueue(upcoming(reminderJson("r1", remindAt = "2030-01-01T04:00:00+00:00")))
        alarms.sync()

        alarms.ring("r1")

        assertEquals(listOf("r1"), notifier.shown)
        assertTrue("r1" !in scheduler.armed)
        assertTrue("r1" !in notifier.cancelled)
    }

    @Test
    fun `Snooze stays on the phone and rings again in ten minutes`() = runTest {
        server.enqueue(upcoming(reminderJson("r1", remindAt = "2030-01-01T04:00:00+00:00")))
        alarms.sync()
        alarms.ring("r1")

        alarms.snooze("r1")

        assertEquals(Instant.parse("2030-01-01T04:10:00Z"), scheduler.armed["r1"])
        assertTrue("r1" in notifier.cancelled)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `Dismiss stops it at once and tells the server`() = runTest {
        server.enqueue(upcoming(reminderJson("r1")))
        server.enqueue(json(reminderJson("r1", dismissedAt = "2030-01-01T04:00:00+00:00")))
        alarms.sync()
        server.takeRequest()

        alarms.dismiss("r1")

        val request = server.takeRequest(5, TimeUnit.SECONDS)!!
        assertEquals("POST", request.method)
        assertEquals("/mobile/v1/reminders/r1/dismiss", request.url.encodedPath)
        assertTrue("r1" !in scheduler.armed)
        assertTrue(store.state.upcoming.isEmpty())
        assertTrue(store.state.pendingDismissals.isEmpty())
    }

    @Test
    fun `a Dismiss made offline is sent on the next sync, and the reminder stays quiet meanwhile`() = runTest {
        server.enqueue(upcoming(reminderJson("r1")))
        server.enqueue(json("""{"message":"Server Error"}""", code = 500))
        alarms.sync()

        alarms.dismiss("r1")
        assertEquals(setOf("r1"), store.state.pendingDismissals)

        server.enqueue(json(reminderJson("r1", dismissedAt = "2030-01-01T04:00:00+00:00")))
        server.enqueue(upcoming())
        alarms.sync()

        assertTrue(store.state.pendingDismissals.isEmpty())
        assertTrue(scheduler.armed.isEmpty())
    }

    @Test
    fun `Done marks the Task done on the server`() = runTest {
        server.enqueue(upcoming(reminderJson("r1", taskId = "t9")))
        server.enqueue(json(taskJson("t9", status = "done")))
        alarms.sync()
        server.takeRequest()

        alarms.done("r1")

        val request = server.takeRequest(5, TimeUnit.SECONDS)!!
        assertEquals("/mobile/v1/tasks/t9/status", request.url.encodedPath)
        assertTrue(store.state.upcoming.isEmpty())
        assertTrue("r1" in notifier.cancelled)
    }

    @Test
    fun `signing out clears every alarm and the stored list`() = runTest {
        server.enqueue(upcoming(reminderJson("r1"), reminderJson("r2", remindAt = "2030-01-01T07:00:00+00:00")))
        alarms.sync()

        alarms.clear()

        assertTrue(scheduler.armed.isEmpty())
        assertEquals(ReminderState(), store.state)
    }
}

private class MemoryReminderStore : ReminderStore {
    var state = ReminderState()

    override suspend fun read(): ReminderState = state

    override suspend fun write(state: ReminderState) {
        this.state = state
    }
}

private class RecordingScheduler : AlarmScheduler {
    val armed = mutableMapOf<String, Instant>()

    override fun arm(reminder: UpcomingReminder, at: Instant) {
        armed[reminder.id] = at
    }

    override fun cancel(reminderId: String) {
        armed.remove(reminderId)
    }
}

private class RecordingNotifier : ReminderNotifier {
    val shown = mutableListOf<String>()
    val cancelled = mutableListOf<String>()

    override fun show(reminder: UpcomingReminder) {
        shown += reminder.id
    }

    override fun cancel(reminderId: String) {
        cancelled += reminderId
    }
}
