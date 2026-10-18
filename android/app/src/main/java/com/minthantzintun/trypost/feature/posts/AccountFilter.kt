package com.minthantzintun.trypost.feature.posts

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.ui.message

/**
 * Filters every tab to one Social account. The accounts load when the menu
 * opens, so a newly connected account shows without restarting the app.
 */
@Composable
fun AccountFilter(
    state: PostsUiState,
    onOpen: () -> Unit,
    onSelect: (String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    var isOpen by rememberSaveable { mutableStateOf(false) }
    val selected = state.selectedAccount
    val allAccounts = stringResource(R.string.account_filter_all)

    Box(modifier = modifier.fillMaxWidth().padding(horizontal = 8.dp)) {
        TextButton(
            onClick = {
                isOpen = true
                onOpen()
            },
            modifier = Modifier.testTag("account-filter"),
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                selected?.let { PlatformLogo(it.platform) }
                Text(
                    (selected?.label ?: allAccounts.takeIf { state.selectedAccountId == null } ?: stringResource(R.string.account_filter_one)),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }

        DropdownMenu(expanded = isOpen, onDismissRequest = { isOpen = false }) {
            DropdownMenuItem(
                text = { Text(allAccounts) },
                onClick = {
                    isOpen = false
                    onSelect(null)
                },
                modifier = Modifier.testTag("account-option-all"),
            )
            state.accounts.forEach { account ->
                DropdownMenuItem(
                    text = {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                            PlatformLogo(account.platform)
                            Text(account.label, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            if (!account.isConnected) {
                                Text(
                                    stringResource(R.string.account_filter_disconnected),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.error,
                                )
                            }
                        }
                    },
                    onClick = {
                        isOpen = false
                        onSelect(account.id)
                    },
                    modifier = Modifier.testTag("account-option-${account.id}"),
                )
            }
            if (state.isLoadingAccounts && state.accounts.isEmpty()) {
                Box(modifier = Modifier.fillMaxWidth().padding(12.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                }
            }
            state.accountsError?.let { error ->
                Text(
                    "${stringResource(R.string.account_filter_error)} ${error.message()}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp).testTag("account-filter-error"),
                )
            }
        }
    }
}
