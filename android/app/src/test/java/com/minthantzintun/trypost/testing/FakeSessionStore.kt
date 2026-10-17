package com.minthantzintun.trypost.testing

import com.minthantzintun.trypost.core.session.Session
import com.minthantzintun.trypost.core.session.SessionStore
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

class FakeSessionStore(initial: Session? = null) : SessionStore {
    private val state = MutableStateFlow(initial)

    override val session: StateFlow<Session?> = state

    override suspend fun current(): Session? = state.value

    override suspend fun save(session: Session) {
        state.value = session
    }

    override suspend fun clear() {
        state.value = null
    }
}
