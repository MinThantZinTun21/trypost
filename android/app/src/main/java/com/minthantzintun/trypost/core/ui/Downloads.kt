package com.minthantzintun.trypost.core.ui

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.listSaver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.core.content.ContextCompat
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.download.DownloadOutcome
import com.minthantzintun.trypost.core.model.DownloadLink
import kotlinx.coroutines.launch

/**
 * What a Download button calls. Android 8–9 save to Downloads only with
 * WRITE_EXTERNAL_STORAGE, so there it asks first and says so when refused;
 * Android 10 and later go straight to [onDownload].
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
            !granted -> scope.launch { snackbarHostState.showSnackbar(refused) }
            link != null -> onDownload(link)
        }
    }

    return { link ->
        val needsPermission = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED

        if (needsPermission) {
            waiting = link
            permission.launch(Manifest.permission.WRITE_EXTERNAL_STORAGE)
        } else {
            onDownload(link)
        }
    }
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
