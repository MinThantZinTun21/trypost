package com.minthantzintun.trypost.feature.edit

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
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
import com.minthantzintun.trypost.core.designsystem.MessageState
import com.minthantzintun.trypost.core.designsystem.PlatformTag
import com.minthantzintun.trypost.core.designsystem.UnderlinedField
import com.minthantzintun.trypost.core.designsystem.asLabel
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
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.edit_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack, modifier = Modifier.testTag("back")) {
                        Icon(painterResource(R.drawable.ic_arrow_back), contentDescription = stringResource(R.string.back))
                    }
                },
                actions = {
                    if (state.isSaving) {
                        CircularProgressIndicator(modifier = Modifier.padding(end = 16.dp).size(24.dp), strokeWidth = 2.dp)
                    } else if (state.post != null) {
                        TextButton(onClick = onSave, modifier = Modifier.testTag("edit-save")) {
                            Text(stringResource(R.string.save).asLabel())
                        }
                    }
                },
            )
        },
    ) { padding ->
        val post = state.post

        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                post == null && state.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
                post == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    MessageState(
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
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                ) {
                    state.saveError?.let {
                        Text(
                            text = it.message(),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.error,
                            modifier = Modifier.testTag("edit-error"),
                        )
                    }

                    UnderlinedField(
                        value = state.content,
                        onValueChange = onContentChange,
                        label = stringResource(R.string.detail_content),
                        singleLine = false,
                        minLines = 4,
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
}

@Composable
private fun PlatformTexts(
    platform: PostPlatform,
    values: Map<PlatformText, String>,
    errors: Map<EditField, String>,
    onTextChange: (platformId: String, text: PlatformText, value: String) -> Unit,
) {
    Surface(
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        shadowElevation = 1.dp,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                PlatformTag(platform.platform)
                Text(
                    text = listOfNotNull(platform.accountName, contentTypeLabel(platform.contentType)).joinToString(" · "),
                    style = MaterialTheme.typography.titleSmall,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            platform.texts.forEach { text ->
                val error = errors[EditField.Text(platform.id, text)]

                UnderlinedField(
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
