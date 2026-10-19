package com.minthantzintun.trypost.feature.signin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.BuildConfig
import com.minthantzintun.trypost.core.data.SessionRepository
import com.minthantzintun.trypost.core.data.SignInResult
import com.minthantzintun.trypost.core.network.ApiError
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class SignInUiState(
    val serverUrl: String,
    val email: String = "",
    val password: String = "",
    val isSubmitting: Boolean = false,
    val error: SignInError? = null,
)

sealed interface SignInError {
    data object InvalidAddress : SignInError

    data object MissingEmail : SignInError

    data object MissingPassword : SignInError

    data class Api(val error: ApiError) : SignInError
}

enum class SignInField { ServerUrl, Email, Password }

/**
 * The field an error shows under. The server reports wrong credentials and
 * the wait after too many tries on `email`; anything not about one field
 * shows under Password, next to Sign in.
 */
val SignInError.field: SignInField
    get() = when (this) {
        SignInError.InvalidAddress -> SignInField.ServerUrl
        SignInError.MissingEmail -> SignInField.Email
        SignInError.MissingPassword -> SignInField.Password
        is SignInError.Api -> when (error) {
            ApiError.NotFound -> SignInField.ServerUrl
            is ApiError.Validation -> if ("email" in error.fields || "password" !in error.fields) SignInField.Email else SignInField.Password
            else -> SignInField.Password
        }
    }

/**
 * Signs the Owner in. On success the stored session changes and the app's
 * root swaps to the post list; this screen only reports failures.
 */
@HiltViewModel
class SignInViewModel @Inject constructor(
    private val sessionRepository: SessionRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(SignInUiState(serverUrl = BuildConfig.DEFAULT_SERVER_URL))
    val state: StateFlow<SignInUiState> = _state.asStateFlow()

    fun onServerUrlChange(value: String) {
        _state.update { it.copy(serverUrl = value, error = null) }
    }

    fun onEmailChange(value: String) {
        _state.update { it.copy(email = value, error = null) }
    }

    fun onPasswordChange(value: String) {
        _state.update { it.copy(password = value, error = null) }
    }

    fun submit() {
        val current = _state.value
        if (current.isSubmitting) {
            return
        }

        _state.update { it.copy(isSubmitting = true, error = null) }

        viewModelScope.launch {
            val error = when (val result = sessionRepository.signIn(current.serverUrl, current.email, current.password)) {
                SignInResult.SignedIn -> null
                SignInResult.InvalidAddress -> SignInError.InvalidAddress
                SignInResult.MissingEmail -> SignInError.MissingEmail
                SignInResult.MissingPassword -> SignInError.MissingPassword
                is SignInResult.Failed -> SignInError.Api(result.error)
            }

            _state.update { it.copy(isSubmitting = false, error = error) }
        }
    }
}
