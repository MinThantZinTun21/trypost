package com.minthantzintun.trypost.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.SessionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

sealed interface RootState {
    data object Loading : RootState

    data object SignedOut : RootState

    data object SignedIn : RootState
}

/** Follows the stored session, so signing out (or a 401) returns to sign-in from anywhere. */
@HiltViewModel
class RootViewModel @Inject constructor(
    sessionRepository: SessionRepository,
) : ViewModel() {
    val state: StateFlow<RootState> = sessionRepository.session
        .map { session -> if (session == null) RootState.SignedOut else RootState.SignedIn }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), RootState.Loading)
}
