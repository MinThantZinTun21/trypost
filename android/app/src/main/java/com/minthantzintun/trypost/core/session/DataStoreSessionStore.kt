package com.minthantzintun.trypost.core.session

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

/** Keeps the [Session] in DataStore, every value encrypted by [SecretCipher]. */
@Singleton
class DataStoreSessionStore @Inject constructor(
    private val dataStore: DataStore<Preferences>,
    private val cipher: SecretCipher,
) : SessionStore {

    override val session: Flow<Session?> = dataStore.data.map { preferences ->
        val serverUrl = preferences[SERVER_URL]?.let(cipher::decrypt)
        val token = preferences[TOKEN]?.let(cipher::decrypt)
        val ownerName = preferences[OWNER_NAME]?.let(cipher::decrypt)

        if (serverUrl == null || token == null || ownerName == null) {
            null
        } else {
            Session(serverUrl = serverUrl, token = token, ownerName = ownerName)
        }
    }

    override suspend fun current(): Session? = session.first()

    override suspend fun save(session: Session) {
        dataStore.edit { preferences ->
            preferences[SERVER_URL] = cipher.encrypt(session.serverUrl)
            preferences[TOKEN] = cipher.encrypt(session.token)
            preferences[OWNER_NAME] = cipher.encrypt(session.ownerName)
        }
    }

    override suspend fun clear() {
        dataStore.edit { it.clear() }
    }

    private companion object {
        val SERVER_URL = stringPreferencesKey("server_url")
        val TOKEN = stringPreferencesKey("token")
        val OWNER_NAME = stringPreferencesKey("owner_name")
    }
}
