package com.minthantzintun.trypost.core.session

import kotlinx.coroutines.flow.Flow

/** Holds the signed-in [Session]; `null` means signed out. */
interface SessionStore {
    val session: Flow<Session?>

    suspend fun current(): Session?

    suspend fun save(session: Session)

    suspend fun clear()
}
