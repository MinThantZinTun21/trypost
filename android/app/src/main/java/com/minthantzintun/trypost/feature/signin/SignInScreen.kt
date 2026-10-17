package com.minthantzintun.trypost.feature.signin

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.UnderlinedField
import com.minthantzintun.trypost.core.designsystem.asLabel
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message

@Composable
fun SignInRoute(viewModel: SignInViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    SignInScreen(
        state = state,
        onServerUrlChange = viewModel::onServerUrlChange,
        onTokenChange = viewModel::onTokenChange,
        onSubmit = viewModel::submit,
    )
}

@Composable
fun SignInScreen(
    state: SignInUiState,
    onServerUrlChange: (String) -> Unit,
    onTokenChange: (String) -> Unit,
    onSubmit: () -> Unit,
) {
    var isTokenVisible by rememberSaveable { mutableStateOf(false) }
    val errorText = when (val error = state.error) {
        null -> null
        SignInError.InvalidAddress -> stringResource(R.string.sign_in_invalid_address)
        SignInError.MissingToken -> stringResource(R.string.sign_in_missing_token)
        is SignInError.Api -> when (error.error) {
            ApiError.Unauthorized -> stringResource(R.string.sign_in_unauthorized)
            ApiError.NotFound -> stringResource(R.string.sign_in_not_found)
            else -> error.error.message()
        }
    }

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp, vertical = 48.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Column(
                modifier = Modifier.widthIn(max = 480.dp).fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Text(text = stringResource(R.string.sign_in_title), style = MaterialTheme.typography.headlineMedium)
                Text(
                    text = stringResource(R.string.sign_in_body),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
                UnderlinedField(
                    value = state.serverUrl,
                    onValueChange = onServerUrlChange,
                    label = stringResource(R.string.sign_in_server_url),
                    isError = state.error == SignInError.InvalidAddress,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri, imeAction = ImeAction.Next, autoCorrectEnabled = false),
                    modifier = Modifier.testTag("sign-in-server-url"),
                )
                UnderlinedField(
                    value = state.token,
                    onValueChange = onTokenChange,
                    label = stringResource(R.string.sign_in_token),
                    isError = state.error is SignInError.Api || state.error == SignInError.MissingToken,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done, autoCorrectEnabled = false),
                    keyboardActions = KeyboardActions(onDone = { onSubmit() }),
                    visualTransformation = if (isTokenVisible) VisualTransformation.None else PasswordVisualTransformation(),
                    trailingIcon = {
                        TextButton(onClick = { isTokenVisible = !isTokenVisible }) {
                            Text(stringResource(if (isTokenVisible) R.string.sign_in_hide_token else R.string.sign_in_show_token).asLabel())
                        }
                    },
                    modifier = Modifier.testTag("sign-in-token"),
                )
                if (errorText != null) {
                    Text(
                        text = errorText,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.error,
                        modifier = Modifier.testTag("sign-in-error"),
                    )
                }
                Button(
                    onClick = onSubmit,
                    enabled = !state.isSubmitting,
                    modifier = Modifier.fillMaxWidth().height(48.dp).testTag("sign-in-submit"),
                ) {
                    if (state.isSubmitting) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp)
                    } else {
                        Text(stringResource(R.string.sign_in_submit).asLabel())
                    }
                }
            }
        }
    }
}
