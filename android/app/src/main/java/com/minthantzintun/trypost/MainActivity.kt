package com.minthantzintun.trypost

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import com.minthantzintun.trypost.core.designsystem.TryPostTheme
import com.minthantzintun.trypost.ui.TryPostApp
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)

        setContent {
            TryPostTheme {
                TryPostApp()
            }
        }
    }
}
