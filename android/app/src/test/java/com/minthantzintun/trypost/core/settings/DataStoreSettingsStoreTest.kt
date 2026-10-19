package com.minthantzintun.trypost.core.settings

import androidx.datastore.preferences.core.PreferenceDataStoreFactory
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder

class DataStoreSettingsStoreTest {
    @get:Rule
    val folder = TemporaryFolder()

    private fun TestScope.store(name: String = "settings") = PreferenceDataStoreFactory.create(
        scope = backgroundScope,
        produceFile = { folder.root.resolve("$name.preferences_pb") },
    )

    @Test
    fun `the theme follows the system until one is chosen, then keeps it`() = runTest(UnconfinedTestDispatcher()) {
        val dataStore = store()
        val settings = DataStoreSettingsStore(dataStore)

        assertEquals(ThemeMode.System, settings.themeMode.first())

        settings.setThemeMode(ThemeMode.Dark)

        assertEquals(ThemeMode.Dark, DataStoreSettingsStore(dataStore).themeMode.first())
    }

    @Test
    fun `an unknown stored theme falls back to the system`() = runTest(UnconfinedTestDispatcher()) {
        val dataStore = store()
        dataStore.edit { it[stringPreferencesKey("theme_mode")] = "sepia" }

        assertEquals(ThemeMode.System, DataStoreSettingsStore(dataStore).themeMode.first())
    }

    @Test
    fun `the Tasks layout is a grid until one is chosen, then keeps it`() = runTest(UnconfinedTestDispatcher()) {
        val dataStore = store()
        val settings = DataStoreSettingsStore(dataStore)

        assertEquals(TaskLayout.Grid, settings.taskLayout.first())

        settings.setTaskLayout(TaskLayout.List)

        assertEquals(TaskLayout.List, DataStoreSettingsStore(dataStore).taskLayout.first())
        assertEquals(ThemeMode.System, DataStoreSettingsStore(dataStore).themeMode.first())
    }
}
