package com.minthantzintun.trypost.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.consumeWindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.navigation.NavDestination.Companion.hasRoute
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.RowScope
import androidx.navigation.NavHostController
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
import com.minthantzintun.trypost.feature.videos.VideosRoute
import kotlinx.serialization.Serializable

@Serializable
data object PostsDestination

@Serializable
data object VideosDestination

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
    val backStackEntry by navController.currentBackStackEntryAsState()
    val destination = backStackEntry?.destination
    val isTopLevel = destination?.hasRoute<PostsDestination>() == true || destination?.hasRoute<VideosDestination>() == true

    Scaffold(
        contentWindowInsets = WindowInsets(0),
        bottomBar = {
            if (isTopLevel) {
                NavigationBar {
                    TopLevelItem(
                        selected = destination?.hasRoute<PostsDestination>() == true,
                        icon = R.drawable.ic_posts,
                        label = R.string.nav_posts,
                        testTag = "nav-posts",
                        onClick = { navController.switchTo(PostsDestination) },
                    )
                    TopLevelItem(
                        selected = destination?.hasRoute<VideosDestination>() == true,
                        icon = R.drawable.ic_videos,
                        label = R.string.nav_videos,
                        testTag = "nav-videos",
                        onClick = { navController.switchTo(VideosDestination) },
                    )
                }
            }
        },
    ) { padding ->
        SignedInGraph(
            navController = navController,
            ownerName = ownerName,
            modifier = Modifier.padding(padding).consumeWindowInsets(padding),
        )
    }
}

@Composable
private fun RowScope.TopLevelItem(selected: Boolean, icon: Int, label: Int, testTag: String, onClick: () -> Unit) {
    NavigationBarItem(
        selected = selected,
        onClick = onClick,
        icon = { Icon(painterResource(icon), contentDescription = null) },
        label = { Text(stringResource(label)) },
        modifier = Modifier.testTag(testTag),
    )
}

/** Switches tabs keeping each tab's own back stack and scroll position. */
private fun NavHostController.switchTo(destination: Any) {
    navigate(destination) {
        popUpTo(graph.findStartDestination().id) { saveState = true }
        launchSingleTop = true
        restoreState = true
    }
}

@Composable
private fun SignedInGraph(navController: NavHostController, ownerName: String, modifier: Modifier) {
    NavHost(navController = navController, startDestination = PostsDestination, modifier = modifier) {
        composable<PostsDestination> {
            PostsRoute(ownerName = ownerName, onOpenPost = { id -> navController.navigate(PostDetailDestination(id)) })
        }
        composable<VideosDestination> {
            VideosRoute()
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
