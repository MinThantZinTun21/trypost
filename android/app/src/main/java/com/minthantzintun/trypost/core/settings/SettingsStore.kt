package com.minthantzintun.trypost.core.settings

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
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

    private companion object {
        val THEME_MODE = stringPreferencesKey("theme_mode")
        val TASK_LAYOUT = stringPreferencesKey("task_layout")
    }
}
