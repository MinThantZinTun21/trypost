package com.minthantzintun.trypost.core.ui

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.width
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.listSaver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.download.DownloadProgress
import com.minthantzintun.trypost.core.model.DownloadLink
import kotlinx.coroutines.launch

/**
 * What a Download button calls. It first asks for the one permission this
 * Android version needs: Android 8–9 save to Downloads only with
 * WRITE_EXTERNAL_STORAGE, so a refusal stops the download and says why;
 * Android 13+ show the progress notification only with POST_NOTIFICATIONS,
 * so the download goes ahead either way. Android 10–12 need neither.
 */
@Composable
fun rememberDownloadAction(snackbarHostState: SnackbarHostState, onDownload: (DownloadLink) -> Unit): (DownloadLink) -> Unit {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val refused = stringResource(R.string.download_needs_storage)
    // Saveable, so a press survives the Activity being recreated behind the permission dialog.
    var waiting by rememberSaveable(stateSaver = DownloadLinkSaver) { mutableStateOf<DownloadLink?>(null) }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        val link = waiting
        waiting = null

        when {
            !granted && Build.VERSION.SDK_INT < Build.VERSION_CODES.Q -> scope.launch { snackbarHostState.showSnackbar(refused) }
            link != null -> onDownload(link)
        }
    }

    return { link ->
        val missing = missingDownloadPermission(context)

        if (missing != null) {
            waiting = link
            permission.launch(missing)
        } else {
            onDownload(link)
        }
    }
}

private fun missingDownloadPermission(context: Context): String? {
    val needed = when {
        Build.VERSION.SDK_INT < Build.VERSION_CODES.Q -> Manifest.permission.WRITE_EXTERNAL_STORAGE
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU -> Manifest.permission.POST_NOTIFICATIONS
        else -> return null
    }

    return needed.takeIf { ContextCompat.checkSelfPermission(context, it) != PackageManager.PERMISSION_GRANTED }
}

/**
 * Shows how the last download went once. The outcome is cleared before the
 * snackbar shows, so leaving the screen never replays it and a second press
 * shows its own message.
 */
@Composable
fun DownloadOutcomeSnackbar(outcome: DownloadOutcome?, snackbarHostState: SnackbarHostState, onShown: () -> Unit) {
    val scope = rememberCoroutineScope()
    val message = when (outcome) {
        null -> null
        DownloadOutcome.Started -> stringResource(R.string.download_started)
        DownloadOutcome.Failed -> stringResource(R.string.download_failed)
    }

    LaunchedEffect(outcome) {
        if (message != null) {
            onShown()
            scope.launch { snackbarHostState.showSnackbar(message) }
        }
    }
}

private val DownloadLinkSaver = listSaver<DownloadLink?, String?>(
    save = { link -> if (link == null) emptyList() else listOf(link.url, link.fileName, link.mimeType) },
    restore = { saved -> saved.takeIf { it.size == 3 }?.let { (url, fileName, mimeType) -> DownloadLink(url!!, fileName!!, mimeType) } },
)

/** Takes a Download button's place while its file downloads: the percent over a small bar. */
@Composable
fun DownloadProgressIndicator(progress: DownloadProgress, modifier: Modifier = Modifier) {
    val fraction = progress.fraction

    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            text = progress.percent?.let { stringResource(R.string.download_percent, it) } ?: stringResource(R.string.download_running),
            style = MaterialTheme.typography.labelSmall,
        )
        if (fraction == null) {
            LinearProgressIndicator(modifier = Modifier.width(56.dp))
        } else {
            LinearProgressIndicator(progress = { fraction }, modifier = Modifier.width(56.dp))
        }
    }
}
