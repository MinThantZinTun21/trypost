package com.minthantzintun.trypost.feature.settings

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ListItem
import androidx.compose.material3.ListItemDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.SectionHeader
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.settings.ThemeMode

@Composable
fun SettingsRoute(onBack: () -> Unit, viewModel: SettingsViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    SettingsScreen(
        state = state,
        onBack = onBack,
        onSelectThemeMode = viewModel::selectThemeMode,
        onSignOut = viewModel::signOut,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    state: SettingsUiState,
    onBack: () -> Unit,
    onSelectThemeMode: (ThemeMode) -> Unit,
    onSignOut: () -> Unit,
) {
    var isConfirmingSignOut by rememberSaveable { mutableStateOf(false) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.settings_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = Spacing.gutter, vertical = Spacing.sm),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Column(
                modifier = Modifier.widthIn(max = 600.dp).fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(Spacing.sm),
            ) {
                SectionHeader(stringResource(R.string.settings_appearance))
                ThemePicker(selected = state.themeMode, onSelect = onSelectThemeMode)
                Text(
                    text = stringResource(R.string.settings_theme_hint),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = Spacing.xs),
                )

                SectionHeader(stringResource(R.string.settings_account), modifier = Modifier.padding(top = Spacing.lg))
                SettingsCard {
                    state.ownerName?.let {
                        SettingsRow(icon = R.drawable.ic_check, headline = R.string.settings_signed_in_as, supporting = it)
                    }
                    state.serverUrl?.let {
                        SettingsRow(icon = R.drawable.ic_server, headline = R.string.settings_server, supporting = it)
                    }
                    ListItem(
                        headlineContent = { Text(stringResource(R.string.sign_out)) },
                        leadingContent = { Icon(painterResource(R.drawable.ic_logout), contentDescription = null) },
                        colors = ListItemDefaults.colors(
                            containerColor = MaterialTheme.colorScheme.surfaceContainerLow,
                            headlineColor = MaterialTheme.colorScheme.error,
                            leadingIconColor = MaterialTheme.colorScheme.error,
                        ),
                        modifier = Modifier
                            .clickable(role = Role.Button) { isConfirmingSignOut = true }
                            .testTag("sign-out"),
                    )
                }

                SectionHeader(stringResource(R.string.settings_about), modifier = Modifier.padding(top = Spacing.lg))
                SettingsCard {
                    SettingsRow(icon = R.drawable.ic_info, headline = R.string.settings_version, supporting = state.appVersion)
                }
            }
        }
    }

    if (isConfirmingSignOut) {
        ConfirmDialog(
            title = stringResource(R.string.sign_out_confirm_title),
            body = stringResource(R.string.sign_out_confirm_body),
            confirmLabel = stringResource(R.string.sign_out),
            onConfirm = {
                isConfirmingSignOut = false
                onSignOut()
            },
            onDismiss = { isConfirmingSignOut = false },
            dismissLabel = stringResource(R.string.cancel),
            icon = R.drawable.ic_logout,
            isDestructive = true,
            confirmTestTag = "sign-out-confirm",
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ThemePicker(selected: ThemeMode, onSelect: (ThemeMode) -> Unit) {
    SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
        ThemeMode.entries.forEachIndexed { index, mode ->
            SegmentedButton(
                selected = mode == selected,
                onClick = { onSelect(mode) },
                shape = SegmentedButtonDefaults.itemShape(index = index, count = ThemeMode.entries.size),
                icon = {
                    SegmentedButtonDefaults.Icon(active = mode == selected) {
                        Icon(painterResource(mode.icon()), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    }
                },
                modifier = Modifier.testTag("theme-${mode.wire}"),
            ) {
                Text(stringResource(mode.label()), maxLines = 1)
            }
        }
    }
}

@Composable
private fun SettingsCard(content: @Composable () -> Unit) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column { content() }
    }
}

@Composable
private fun SettingsRow(@DrawableRes icon: Int, @StringRes headline: Int, supporting: String) {
    ListItem(
        headlineContent = { Text(stringResource(headline)) },
        supportingContent = { Text(supporting) },
        leadingContent = { Icon(painterResource(icon), contentDescription = null) },
        colors = ListItemDefaults.colors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    )
}

@DrawableRes
private fun ThemeMode.icon(): Int = when (this) {
    ThemeMode.System -> R.drawable.ic_theme_system
    ThemeMode.Dark -> R.drawable.ic_theme_dark
    ThemeMode.Light -> R.drawable.ic_theme_light
}

@StringRes
private fun ThemeMode.label(): Int = when (this) {
    ThemeMode.System -> R.string.theme_system
    ThemeMode.Dark -> R.string.theme_dark
    ThemeMode.Light -> R.string.theme_light
}
