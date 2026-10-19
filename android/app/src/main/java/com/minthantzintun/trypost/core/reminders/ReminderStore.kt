package com.minthantzintun.trypost.core.reminders

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import kotlinx.coroutines.flow.first
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import javax.inject.Inject
import javax.inject.Qualifier
import javax.inject.Singleton

/** The phone's copy of the reminders, so they ring and re-arm after a reboot with no connection. */
@Serializable
data class ReminderState(
    val upcoming: List<UpcomingReminder> = emptyList(),
    val rings: Map<String, ReminderRing> = emptyMap(),
    /** The reminders with an alarm set, so one that is gone has its alarm cancelled. */
    val armedIds: Set<String> = emptySet(),
    /** Dismissals the server has not taken yet, e.g. made with no connection. */
    val pendingDismissals: Set<String> = emptySet(),
)

interface ReminderStore {
    suspend fun read(): ReminderState

    suspend fun write(state: ReminderState)
}

/** The DataStore that holds [ReminderStore], apart from the session and settings. */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class ReminderPreferences

@Singleton
class DataStoreReminderStore @Inject constructor(
    @ReminderPreferences private val dataStore: DataStore<Preferences>,
    private val json: Json,
) : ReminderStore {
    override suspend fun read(): ReminderState {
        val raw = dataStore.data.first()[STATE] ?: return ReminderState()

        return runCatching { json.decodeFromString(ReminderState.serializer(), raw) }.getOrDefault(ReminderState())
    }

    override suspend fun write(state: ReminderState) {
        dataStore.edit { it[STATE] = json.encodeToString(ReminderState.serializer(), state) }
    }

    private companion object {
        val STATE = stringPreferencesKey("reminder_state")
    }
}
