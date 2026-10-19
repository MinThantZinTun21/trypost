package com.minthantzintun.trypost.core.settings

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Qualifier
import javax.inject.Singleton

/** The app's own preferences. They outlive the session: Sign out keeps them. */
interface SettingsStore {
    val themeMode: Flow<ThemeMode>

    suspend fun setThemeMode(mode: ThemeMode)

    /** The Tasks list's layout, kept across launches. */
    val taskLayout: Flow<TaskLayout>

    suspend fun setTaskLayout(layout: TaskLayout)

    /** A Pomodoro's length, in minutes. */
    val pomodoroFocusMinutes: Flow<Int>

    suspend fun setPomodoroFocusMinutes(minutes: Int)

    /** The break after a Pomodoro, in minutes. */
    val pomodoroBreakMinutes: Flow<Int>

    suspend fun setPomodoroBreakMinutes(minutes: Int)

    companion object {
        const val DEFAULT_FOCUS_MINUTES = 25
        const val DEFAULT_BREAK_MINUTES = 5

        /** The lengths Settings offers; the server takes 1 to 240 minutes. */
        val FocusChoices = listOf(15, 20, 25, 30, 45, 50, 60, 90)
        val BreakChoices = listOf(3, 5, 10, 15, 20, 30)
    }
}

/** The DataStore that holds [SettingsStore], apart from the session's. */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class SettingsPreferences

@Singleton
class DataStoreSettingsStore @Inject constructor(
    @SettingsPreferences private val dataStore: DataStore<Preferences>,
) : SettingsStore {

    override val themeMode: Flow<ThemeMode> = dataStore.data
        .map { ThemeMode.fromWire(it[THEME_MODE]) }
        .distinctUntilChanged()

    override suspend fun setThemeMode(mode: ThemeMode) {
        dataStore.edit { it[THEME_MODE] = mode.wire }
    }

    override val taskLayout: Flow<TaskLayout> = dataStore.data
        .map { TaskLayout.fromWire(it[TASK_LAYOUT]) }
        .distinctUntilChanged()

    override suspend fun setTaskLayout(layout: TaskLayout) {
        dataStore.edit { it[TASK_LAYOUT] = layout.wire }
    }

    override val pomodoroFocusMinutes: Flow<Int> = dataStore.data
        .map { it[POMODORO_FOCUS] ?: SettingsStore.DEFAULT_FOCUS_MINUTES }
        .distinctUntilChanged()

    override suspend fun setPomodoroFocusMinutes(minutes: Int) {
        dataStore.edit { it[POMODORO_FOCUS] = minutes }
    }

    override val pomodoroBreakMinutes: Flow<Int> = dataStore.data
        .map { it[POMODORO_BREAK] ?: SettingsStore.DEFAULT_BREAK_MINUTES }
        .distinctUntilChanged()

    override suspend fun setPomodoroBreakMinutes(minutes: Int) {
        dataStore.edit { it[POMODORO_BREAK] = minutes }
    }

    private companion object {
        val THEME_MODE = stringPreferencesKey("theme_mode")
        val TASK_LAYOUT = stringPreferencesKey("task_layout")
        val POMODORO_FOCUS = intPreferencesKey("pomodoro_focus_minutes")
        val POMODORO_BREAK = intPreferencesKey("pomodoro_break_minutes")
    }
}
