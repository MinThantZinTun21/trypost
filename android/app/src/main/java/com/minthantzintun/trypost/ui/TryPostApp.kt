package com.minthantzintun.trypost.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.asLabel
import com.minthantzintun.trypost.feature.detail.PostDetailRoute
import com.minthantzintun.trypost.feature.edit.EditPostRoute
import com.minthantzintun.trypost.feature.posts.PostsRoute
import com.minthantzintun.trypost.feature.signin.SignInRoute
import kotlinx.serialization.Serializable

@Serializable
data object PostsDestination

@Serializable
data class PostDetailDestination(val id: String)

@Serializable
data class EditPostDestination(val id: String)

@Composable
fun TryPostApp(viewModel: RootViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        when (val current = state) {
            RootState.Loading -> Box(Modifier.fillMaxSize())
            RootState.SignedOut -> SignInRoute()
            is RootState.SignedIn -> SignedInNavHost(ownerName = current.ownerName)
        }
    }
}

@Composable
private fun SignedInNavHost(ownerName: String) {
    val navController = rememberNavController()

    NavHost(navController = navController, startDestination = PostsDestination) {
        composable<PostsDestination> {
            PostsRoute(ownerName = ownerName, onOpenPost = { id -> navController.navigate(PostDetailDestination(id)) })
        }
        composable<PostDetailDestination> {
            PostDetailRoute(
                onBack = { navController.popBackStack() },
                extraActions = { post ->
                    OutlinedButton(
                        onClick = { navController.navigate(EditPostDestination(post.id)) },
                        modifier = Modifier.testTag("edit"),
                    ) {
                        Text(stringResource(R.string.edit).asLabel())
                    }
                },
            )
        }
        composable<EditPostDestination> {
            EditPostRoute(
                onBack = { navController.popBackStack() },
                onSaved = { navController.popBackStack() },
            )
        }
    }
}
