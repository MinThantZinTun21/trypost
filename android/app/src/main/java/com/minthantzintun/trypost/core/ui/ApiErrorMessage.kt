package com.minthantzintun.trypost.core.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.network.ApiError

@Composable
fun ApiError.message(): String = when (this) {
    ApiError.Unauthorized -> stringResource(R.string.error_unauthorized)
    ApiError.NotFound -> stringResource(R.string.error_not_found)
    ApiError.Unreachable -> stringResource(R.string.error_unreachable)
    is ApiError.Conflict -> message ?: stringResource(R.string.error_conflict)
    is ApiError.Validation -> message ?: stringResource(R.string.error_validation)
    is ApiError.Server -> code?.let { stringResource(R.string.error_server, it.toString()) }
        ?: stringResource(R.string.error_server_unknown)
}
