package com.minthantzintun.trypost.feature.posts

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AssistChip
import androidx.compose.material3.AssistChipDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing

/**
 * Filters every tab to one Social account: All first, then each account with
 * its network's logo. A disconnected account carries a warning mark.
 */
@Composable
fun AccountFilter(
    state: PostsUiState,
    onRetry: () -> Unit,
    onSelect: (String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    if (state.accounts.isEmpty() && !state.isLoadingAccounts && state.accountsError == null) {
        return
    }

    LazyRow(
        contentPadding = PaddingValues(horizontal = Spacing.gutter, vertical = Spacing.sm),
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
        modifier = modifier.testTag("account-filter"),
    ) {
        item(key = "all") {
            FilterChip(
                selected = state.selectedAccountId == null,
                onClick = { onSelect(null) },
                label = { Text(stringResource(R.string.account_filter_all)) },
                modifier = Modifier.testTag("account-option-all"),
            )
        }
        items(state.accounts, key = { it.id }) { account ->
            FilterChip(
                selected = account.id == state.selectedAccountId,
                onClick = { onSelect(account.id) },
                label = { Text(account.label, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.widthIn(max = 160.dp)) },
                leadingIcon = { PlatformLogo(account.platform) },
                trailingIcon = if (account.isConnected) {
                    null
                } else {
                    {
                        Icon(
                            painter = painterResource(R.drawable.ic_status_partial),
                            contentDescription = stringResource(R.string.account_filter_disconnected),
                            tint = MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(IconSize.sm),
                        )
                    }
                },
                modifier = Modifier.testTag("account-option-${account.id}"),
            )
        }
        if (state.isLoadingAccounts && state.accounts.isEmpty()) {
            items(3) {
                Skeleton(modifier = Modifier.width(112.dp).height(32.dp), shape = MaterialTheme.shapes.small)
            }
        }
        if (state.accountsError != null && state.accounts.isEmpty()) {
            item(key = "error") {
                AssistChip(
                    onClick = onRetry,
                    label = { Text(stringResource(R.string.posts_accounts_retry)) },
                    leadingIcon = {
                        Icon(painterResource(R.drawable.ic_cloud_off), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                    },
                    colors = AssistChipDefaults.assistChipColors(
                        labelColor = MaterialTheme.colorScheme.error,
                        leadingIconContentColor = MaterialTheme.colorScheme.error,
                    ),
                    modifier = Modifier.testTag("account-filter-error"),
                )
            }
        }
    }
}
