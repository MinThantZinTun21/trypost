package com.minthantzintun.trypost

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import com.minthantzintun.trypost.core.designsystem.TryPostTheme
import com.minthantzintun.trypost.core.di.ApplicationScope
import com.minthantzintun.trypost.core.notifications.AppNotifications
import com.minthantzintun.trypost.core.reminders.ReminderAlarms
import com.minthantzintun.trypost.core.timer.TimerTracker
import com.minthantzintun.trypost.ui.AppThemeViewModel
import com.minthantzintun.trypost.ui.TryPostApp
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    private val themeViewModel: AppThemeViewModel by viewModels()

    @Inject
    lateinit var reminderAlarms: ReminderAlarms

    @Inject
    lateinit var timerTracker: TimerTracker

    @Inject
    @ApplicationScope
    lateinit var applicationScope: CoroutineScope

    /** A Task a notification asked to open, until the app has shown it. */
    private var taskToOpen by mutableStateOf<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        if (savedInstanceState == null) {
            handle(intent)
        }

        setContent {
            val themeMode by themeViewModel.themeMode.collectAsStateWithLifecycle()
            val darkTheme = themeMode?.isDark(isSystemInDarkTheme()) ?: isSystemInDarkTheme()

            // The system bar icons follow the app's theme, not the phone's.
            LaunchedEffect(darkTheme) {
                enableEdgeToEdge(
                    statusBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT) { darkTheme },
                    navigationBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT) { darkTheme },
                )
            }

            if (themeMode != null) {
                TryPostTheme(darkTheme = darkTheme) {
                    TryPostApp(taskToOpen = taskToOpen, onTaskOpened = { taskToOpen = null })
                }
            }
        }
    }

    override fun onStart() {
        super.onStart()
        // In front, the app may start the timer's service again.
        lifecycleScope.launch { timerTracker.refresh() }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handle(intent)
    }

    /** A notification's Open: show the Task, and dismiss the reminder that rang. */
    private fun handle(intent: Intent) {
        intent.getStringExtra(AppNotifications.EXTRA_DISMISS_REMINDER_ID)?.let { reminderId ->
            applicationScope.launch { reminderAlarms.dismiss(reminderId) }
        }
        intent.getStringExtra(AppNotifications.EXTRA_TASK_ID)?.let { taskToOpen = it }
    }
}
