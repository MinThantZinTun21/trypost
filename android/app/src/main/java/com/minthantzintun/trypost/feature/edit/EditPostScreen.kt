package com.minthantzintun.trypost.feature.edit

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.ConfirmDialog
import com.minthantzintun.trypost.core.designsystem.FormField
import com.minthantzintun.trypost.core.designsystem.IconSize
import com.minthantzintun.trypost.core.designsystem.PlatformLogo
import com.minthantzintun.trypost.core.designsystem.Skeleton
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StateMessage
import com.minthantzintun.trypost.core.model.PlatformText
import com.minthantzintun.trypost.core.model.PostPlatform
import com.minthantzintun.trypost.core.ui.contentTypeLabel
import com.minthantzintun.trypost.core.ui.label
import com.minthantzintun.trypost.core.ui.message

@Composable
fun EditPostRoute(
    onBack: () -> Unit,
    onSaved: () -> Unit,
    viewModel: EditPostViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(state.isSaved) {
        if (state.isSaved) {
            onSaved()
        }
    }

    EditPostScreen(
        state = state,
        onBack = onBack,
        onSave = viewModel::save,
        onRetry = viewModel::retry,
        onContentChange = viewModel::changeContent,
        onTextChange = viewModel::changeText,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EditPostScreen(
    state: EditPostUiState,
    onBack: () -> Unit,
    onSave: () -> Unit,
    onRetry: () -> Unit,
    onContentChange: (String) -> Unit,
    onTextChange: (platformId: String, text: PlatformText, value: String) -> Unit,
) {
    var isConfirmingDiscard by rememberSaveable { mutableStateOf(false) }
    val leave = {
        if (state.hasUnsavedChanges) {
            isConfirmingDiscard = true
        } else {
            onBack()
        }
    }

    BackHandler(enabled = state.hasUnsavedChanges, onBack = leave)

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.edit_title)) },
                navigationIcon = {
                    IconButton(onClick = leave, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
                actions = {
                    if (state.post != null) {
                        Button(
                            onClick = onSave,
                            enabled = !state.isSaving,
                            contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                            modifier = Modifier.padding(end = Spacing.sm).testTag("edit-save"),
                        ) {
                            if (state.isSaving) {
                                CircularProgressIndicator(modifier = Modifier.size(IconSize.sm), strokeWidth = 2.dp)
                            } else {
                                Icon(painterResource(R.drawable.ic_check), contentDescription = null, modifier = Modifier.size(IconSize.sm))
                            }
                            Text(stringResource(R.string.save), modifier = Modifier.padding(start = Spacing.sm))
                        }
                    }
                },
            )
        },
    ) { padding ->
        val post = state.post

        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                post == null && state.isLoading -> Column(
                    modifier = Modifier.fillMaxSize().padding(Spacing.gutter),
                    verticalArrangement = Arrangement.spacedBy(Spacing.lg),
                ) {
                    Skeleton(Modifier.fillMaxWidth().height(140.dp), shape = MaterialTheme.shapes.small)
                    Skeleton(Modifier.fillMaxWidth().height(180.dp), shape = MaterialTheme.shapes.large)
                }
                post == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = R.drawable.ic_cloud_off,
                        title = stringResource(R.string.detail_error_title),
                        body = state.loadError?.message().orEmpty(),
                        actionLabel = stringResource(R.string.retry),
                        onAction = onRetry,
                        isError = true,
                    )
                }
                else -> Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .imePadding()
                        .verticalScroll(rememberScrollState())
                        .padding(Spacing.gutter),
                    verticalArrangement = Arrangement.spacedBy(Spacing.lg),
                ) {
                    state.saveError?.let {
                        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), modifier = Modifier.testTag("edit-error")) {
                            Icon(
                                painter = painterResource(R.drawable.ic_status_failed),
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.error,
                                modifier = Modifier.size(IconSize.sm),
                            )
                            Text(text = it.message(), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.error)
                        }
                    }

                    FormField(
                        value = state.content,
                        onValueChange = onContentChange,
                        label = stringResource(R.string.detail_content),
                        singleLine = false,
                        minLines = 4,
                        enabled = !state.isSaving,
                        isError = state.fieldErrors.containsKey(EditField.Content),
                        supportingText = state.fieldErrors[EditField.Content] ?: stringResource(R.string.edit_content_hint),
                        modifier = Modifier.testTag("edit-content"),
                    )

                    post.platforms.filter { it.texts.isNotEmpty() }.forEach { platform ->
                        PlatformTexts(
                            platform = platform,
                            values = state.texts[platform.id].orEmpty(),
                            errors = state.fieldErrors,
                            onTextChange = onTextChange,
                        )
                    }
                }
            }
        }
    }

    if (isConfirmingDiscard) {
        ConfirmDialog(
            title = stringResource(R.string.edit_discard_title),
            body = stringResource(R.string.edit_discard_body),
            confirmLabel = stringResource(R.string.edit_discard),
            onConfirm = {
                isConfirmingDiscard = false
                onBack()
            },
            onDismiss = { isConfirmingDiscard = false },
            dismissLabel = stringResource(R.string.edit_keep_editing),
            isDestructive = true,
            confirmTestTag = "edit-discard",
        )
    }
}

@Composable
private fun PlatformTexts(
    platform: PostPlatform,
    values: Map<PlatformText, String>,
    errors: Map<EditField, String>,
    onTextChange: (platformId: String, text: PlatformText, value: String) -> Unit,
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(modifier = Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.md), verticalAlignment = Alignment.CenterVertically) {
                PlatformLogo(platform.platform, size = IconSize.md)
                Text(
                    text = listOfNotNull(platform.accountName, contentTypeLabel(platform.contentType)).joinToString(" · "),
                    style = MaterialTheme.typography.titleMedium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            platform.texts.forEach { text ->
                val error = errors[EditField.Text(platform.id, text)]

                FormField(
                    value = values[text].orEmpty(),
                    onValueChange = { onTextChange(platform.id, text, it) },
                    label = text.label(),
                    singleLine = text == PlatformText.Title || text == PlatformText.Tags,
                    minLines = if (text == PlatformText.Description || text == PlatformText.Caption) 3 else 1,
                    isError = error != null,
                    supportingText = error ?: if (text == PlatformText.Tags) stringResource(R.string.edit_tags_hint) else null,
                    modifier = Modifier.testTag("edit-${platform.id}-${text.metaKey}"),
                )
            }
        }
    }
}
