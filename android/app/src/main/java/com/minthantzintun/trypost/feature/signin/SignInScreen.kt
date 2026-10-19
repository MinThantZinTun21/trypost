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
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentType
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.AppMark
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.ui.message

@Composable
fun SignInRoute(viewModel: SignInViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    SignInScreen(
        state = state,
        onServerUrlChange = viewModel::onServerUrlChange,
        onEmailChange = viewModel::onEmailChange,
        onPasswordChange = viewModel::onPasswordChange,
        onSubmit = viewModel::submit,
    )
}

@Composable
fun SignInScreen(
    state: SignInUiState,
    onServerUrlChange: (String) -> Unit,
    onEmailChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onSubmit: () -> Unit,
) {
    var isPasswordVisible by rememberSaveable { mutableStateOf(false) }
    val errorText = when (val error = state.error) {
        null -> null
        SignInError.InvalidAddress -> stringResource(R.string.sign_in_invalid_address)
        SignInError.MissingEmail -> stringResource(R.string.sign_in_missing_email)
        SignInError.MissingPassword -> stringResource(R.string.sign_in_missing_password)
        is SignInError.Api -> when (error.error) {
            ApiError.NotFound -> stringResource(R.string.sign_in_not_found)
            else -> error.error.message()
        }
    }
    val isAddressError = state.error == SignInError.InvalidAddress || (state.error as? SignInError.Api)?.error == ApiError.NotFound
    val isEmailError = state.error == SignInError.MissingEmail
    val isPasswordError = state.error != null && !isAddressError && !isEmailError

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = Spacing.xl, vertical = 48.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Column(
                modifier = Modifier.widthIn(max = 480.dp).fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(Spacing.lg),
            ) {
                AppMark()
                Spacer(Modifier.height(Spacing.xs))
                Text(
                    text = stringResource(R.string.sign_in_title),
                    style = MaterialTheme.typography.headlineMedium,
                    modifier = Modifier.semantics { heading() },
                )
                Text(
                    text = stringResource(R.string.sign_in_body),
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(Spacing.sm))
                FormField(
                    value = state.serverUrl,
                    onValueChange = onServerUrlChange,
                    label = stringResource(R.string.sign_in_server_url),
                    isError = isAddressError,
                    supportingText = errorText?.takeIf { isAddressError },
                    enabled = !state.isSubmitting,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri, imeAction = ImeAction.Next, autoCorrectEnabled = false),
                    leadingIcon = { Icon(painterResource(R.drawable.ic_server), contentDescription = null) },
                    modifier = Modifier.testTag("sign-in-server-url"),
                )
                FormField(
                    value = state.email,
                    onValueChange = onEmailChange,
                    label = stringResource(R.string.sign_in_email),
                    isError = isEmailError,
                    supportingText = errorText?.takeIf { isEmailError },
                    enabled = !state.isSubmitting,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next, autoCorrectEnabled = false),
                    leadingIcon = { Icon(painterResource(R.drawable.ic_mail), contentDescription = null) },
                    modifier = Modifier
                        .semantics { contentType = ContentType.Username + ContentType.EmailAddress }
                        .testTag("sign-in-email"),
                )
                FormField(
                    value = state.password,
                    onValueChange = onPasswordChange,
                    label = stringResource(R.string.sign_in_password),
                    isError = isPasswordError,
                    supportingText = errorText?.takeIf { isPasswordError },
                    enabled = !state.isSubmitting,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done, autoCorrectEnabled = false),
                    keyboardActions = KeyboardActions(onDone = { onSubmit() }),
                    visualTransformation = if (isPasswordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                    leadingIcon = { Icon(painterResource(R.drawable.ic_lock), contentDescription = null) },
                    trailingIcon = {
                        IconButton(onClick = { isPasswordVisible = !isPasswordVisible }) {
                            Icon(
                                painter = painterResource(if (isPasswordVisible) R.drawable.ic_eye_off else R.drawable.ic_eye),
                                contentDescription = stringResource(if (isPasswordVisible) R.string.sign_in_hide_password else R.string.sign_in_show_password),
                            )
                        }
                    },
                    modifier = Modifier
                        .semantics { contentType = ContentType.Password }
                        .testTag("sign-in-password"),
                )
                Button(
                    onClick = onSubmit,
                    enabled = !state.isSubmitting,
                    modifier = Modifier.fillMaxWidth().height(IconSize.touchTarget + Spacing.xs).testTag("sign-in-submit"),
                ) {
                    if (state.isSubmitting) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(IconSize.sm),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                    } else {
                        Text(stringResource(R.string.sign_in_submit))
                    }
                }
            }
        }
    }
}
