package com.minthantzintun.trypost.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.RowScope
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
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavDestination.Companion.hasRoute
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.feature.detail.PostDetailRoute
import com.minthantzintun.trypost.feature.edit.EditPostRoute
import com.minthantzintun.trypost.feature.posts.PostsRoute
import com.minthantzintun.trypost.feature.settings.SettingsRoute
import com.minthantzintun.trypost.feature.signin.SignInRoute
import com.minthantzintun.trypost.feature.tasks.TaskDetailRoute
import com.minthantzintun.trypost.feature.tasks.TaskEditorRoute
import com.minthantzintun.trypost.feature.tasks.TasksRoute
import com.minthantzintun.trypost.feature.videos.VideosRoute
import kotlinx.serialization.Serializable

@Serializable
data object PostsDestination

@Serializable
data object TasksDestination

@Serializable
data object VideosDestination

@Serializable
data object SettingsDestination

@Serializable
data class PostDetailDestination(val id: String)

@Serializable
data class EditPostDestination(val id: String)

@Serializable
data class TaskDetailDestination(val id: String)

/** A new Task; [startsAsChecklist] opens the body on an empty checkbox. */
@Serializable
data class NewTaskDestination(val startsAsChecklist: Boolean = false)

@Serializable
data class EditTaskDestination(val id: String)

private val TopLevelDestinations = listOf(PostsDestination::class, TasksDestination::class, VideosDestination::class, SettingsDestination::class)

@Composable
fun TryPostApp(viewModel: RootViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        when (state) {
            RootState.Loading -> Box(Modifier.fillMaxSize())
            RootState.SignedOut -> SignInRoute()
            is RootState.SignedIn -> SignedInNavHost()
        }
    }
}

@Composable
private fun SignedInNavHost() {
    val navController = rememberNavController()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val destination = backStackEntry?.destination
    val isTopLevel = destination != null && TopLevelDestinations.any { destination.hasRoute(it) }

    Scaffold(
        contentWindowInsets = WindowInsets(0),
        bottomBar = {
            if (isTopLevel) {
                NavigationBar {
                    TopLevelItem(
                        selected = destination.hasRoute<PostsDestination>(),
                        icon = R.drawable.ic_posts,
                        label = R.string.nav_posts,
                        testTag = "nav-posts",
                        onClick = { navController.switchTo(PostsDestination) },
                    )
                    TopLevelItem(
                        selected = destination.hasRoute<TasksDestination>(),
                        icon = R.drawable.ic_tasks,
                        label = R.string.nav_tasks,
                        testTag = "nav-tasks",
                        onClick = { navController.switchTo(TasksDestination) },
                    )
                    TopLevelItem(
                        selected = destination.hasRoute<VideosDestination>(),
                        icon = R.drawable.ic_videos,
                        label = R.string.nav_videos,
                        testTag = "nav-videos",
                        onClick = { navController.switchTo(VideosDestination) },
                    )
                    TopLevelItem(
                        selected = destination.hasRoute<SettingsDestination>(),
                        icon = R.drawable.ic_settings,
                        label = R.string.nav_settings,
                        testTag = "nav-settings",
                        onClick = { navController.switchTo(SettingsDestination) },
                    )
                }
            }
        },
    ) { padding ->
        SignedInGraph(
            navController = navController,
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
private fun SignedInGraph(navController: NavHostController, modifier: Modifier) {
    NavHost(navController = navController, startDestination = PostsDestination, modifier = modifier) {
        composable<PostsDestination> {
            PostsRoute(onOpenPost = { id -> navController.navigate(PostDetailDestination(id)) })
        }
        composable<TasksDestination> {
            TasksRoute(
                onOpenTask = { id -> navController.navigate(TaskDetailDestination(id)) },
                onNewTask = { isChecklist -> navController.navigate(NewTaskDestination(startsAsChecklist = isChecklist)) },
            )
        }
        composable<VideosDestination> {
            VideosRoute()
        }
        composable<SettingsDestination> {
            SettingsRoute()
        }
        composable<TaskDetailDestination> {
            TaskDetailRoute(
                onBack = { navController.popBackStack() },
                onEdit = { id -> navController.navigate(EditTaskDestination(id)) },
            )
        }
        composable<NewTaskDestination> {
            TaskEditorRoute(
                onBack = { navController.popBackStack() },
                onSaved = { navController.popBackStack() },
            )
        }
        composable<EditTaskDestination> {
            TaskEditorRoute(
                onBack = { navController.popBackStack() },
                onSaved = { navController.popBackStack() },
            )
        }
        composable<PostDetailDestination> {
            PostDetailRoute(
                onBack = { navController.popBackStack() },
                onEdit = { id -> navController.navigate(EditPostDestination(id)) },
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
