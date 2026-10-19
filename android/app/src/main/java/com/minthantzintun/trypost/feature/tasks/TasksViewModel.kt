package com.minthantzintun.trypost.feature.tasks

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.TasksRepository
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskTab
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import com.minthantzintun.trypost.core.settings.SettingsStore
import com.minthantzintun.trypost.core.settings.TaskLayout
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** One tab's loaded pages. `page` 0 means nothing has loaded yet. */
data class TaskTabState(
    val tasks: List<Task> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 1,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val error: ApiError? = null,
    val loadMoreError: ApiError? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val hasMore: Boolean get() = hasLoaded && page < lastPage
}

data class TasksUiState(
    val selectedTab: TaskTab = TaskTab.Open,
    val tabs: Map<TaskTab, TaskTabState> = TaskTab.entries.associateWith { TaskTabState() },
    /** What the search field shows; the lists follow it after a short pause. */
    val query: String = "",
    /** The Task tags every tab is filtered to, in the order they were picked. */
    val taskTagIds: List<String> = emptyList(),
) {
    val current: TaskTabState get() = tabs.getValue(selectedTab)
    val isFiltered: Boolean get() = query.isNotBlank() || taskTagIds.isNotEmpty()
}

/**
 * The Tasks list: Open and Done tabs, each paged, searched and filtered by
 * Task tags. The server reads the Task tags per Tag group (AND across
 * groups, OR within one); the app only sends them.
 */
@HiltViewModel
class TasksViewModel @Inject constructor(
    private val tasksRepository: TasksRepository,
    private val settingsStore: SettingsStore,
) : ViewModel() {

    private val _state = MutableStateFlow(TasksUiState())
    val state: StateFlow<TasksUiState> = _state.asStateFlow()

    /** Two columns or one, as the Owner last chose. */
    val layout: StateFlow<TaskLayout> = settingsStore.taskLayout
        .stateIn(viewModelScope, SharingStarted.Eagerly, TaskLayout.Grid)

    private val jobs = mutableMapOf<TaskTab, Job>()
    private var searchJob: Job? = null

    /** The query the loaded tabs were asked with. */
    private var appliedQuery: String = ""

    init {
        loadFirstPage(TaskTab.Open, isRefresh = false)

        viewModelScope.launch {
            tasksRepository.changes.collect { reloadLoadedTabs() }
        }
    }

    /** Switches between the grid and the list, and remembers it. */
    fun toggleLayout() {
        val next = if (layout.value == TaskLayout.Grid) TaskLayout.List else TaskLayout.Grid
        viewModelScope.launch { settingsStore.setTaskLayout(next) }
    }

    fun selectTab(tab: TaskTab) {
        _state.update { it.copy(selectedTab = tab) }

        val tabState = _state.value.tabs.getValue(tab)
        if (!tabState.hasLoaded && !tabState.isLoading) {
            loadFirstPage(tab, isRefresh = false)
        }
    }

    /** Updates the search field and searches once typing pauses. */
    fun changeQuery(query: String) {
        _state.update { it.copy(query = query) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MS)
            applyQuery()
        }
    }

    /** Searches now, e.g. from the keyboard's search key. */
    fun search() {
        searchJob?.cancel()
        applyQuery()
    }

    /** Filters every tab to [taskTagIds]; an empty list drops the filter. */
    fun filterByTaskTags(taskTagIds: List<String>) {
        if (taskTagIds == _state.value.taskTagIds) {
            return
        }

        _state.update { it.copy(taskTagIds = taskTagIds) }
        reloadFromScratch()
    }

    fun removeTaskTagFilter(id: String) {
        filterByTaskTags(_state.value.taskTagIds - id)
    }

    /** Drops the search and every Task tag filter. */
    fun clearFilters() {
        searchJob?.cancel()
        val hadFilters = appliedQuery.isNotEmpty() || _state.value.taskTagIds.isNotEmpty()
        appliedQuery = ""
        _state.update { it.copy(query = "", taskTagIds = emptyList()) }
        if (hadFilters) {
            reloadFromScratch()
        }
    }

    /**
     * Drops filters on Task tags that no longer exist (deleted here or on
     * another screen), since the server refuses an unknown one.
     */
    fun keepOnlyKnownTaskTags(knownIds: Set<String>) {
        val kept = _state.value.taskTagIds.filter { it in knownIds }
        if (kept.size != _state.value.taskTagIds.size) {
            filterByTaskTags(kept)
        }
    }

    /** Pull to refresh: reloads the first page of the selected tab. */
    fun refresh() {
        loadFirstPage(_state.value.selectedTab, isRefresh = true)
    }

    fun retry() {
        loadFirstPage(_state.value.selectedTab, isRefresh = false)
    }

    /** Reloads every tab that has loaded, e.g. after a Task changed on another screen. */
    fun reloadLoadedTabs() {
        _state.value.tabs
            .filterValues { it.hasLoaded }
            .keys
            .forEach { loadFirstPage(it, isRefresh = true) }
    }

    fun loadMore() {
        val tab = _state.value.selectedTab
        val tabState = _state.value.tabs.getValue(tab)
        if (!tabState.hasMore || tabState.isLoadingMore || tabState.isLoading || tabState.isRefreshing) {
            return
        }

        updateTab(tab) { it.copy(isLoadingMore = true, loadMoreError = null) }

        jobs[tab] = viewModelScope.launch {
            when (val result = tasksRepository.page(tab, tabState.page + 1, _state.value.taskTagIds, appliedQuery)) {
                is ApiResult.Success -> updateTab(tab) { current ->
                    val knownIds = current.tasks.mapTo(HashSet()) { it.id }
                    current.copy(
                        tasks = current.tasks + result.value.tasks.filterNot { it.id in knownIds },
                        page = result.value.page,
                        lastPage = result.value.lastPage,
                        isLoadingMore = false,
                    )
                }
                is ApiResult.Failure -> updateTab(tab) { it.copy(isLoadingMore = false, loadMoreError = result.error) }
            }
        }
    }

    private fun applyQuery() {
        val query = _state.value.query.trim()
        if (query == appliedQuery) {
            return
        }

        appliedQuery = query
        reloadFromScratch()
    }

    /** Drops every loaded tab, so each one loads again with the current search and filter. */
    private fun reloadFromScratch() {
        jobs.values.forEach { it.cancel() }
        jobs.clear()
        _state.update { it.copy(tabs = TaskTab.entries.associateWith { TaskTabState() }) }
        loadFirstPage(_state.value.selectedTab, isRefresh = false)
    }

    private fun loadFirstPage(tab: TaskTab, isRefresh: Boolean) {
        jobs[tab]?.cancel()
        updateTab(tab) {
            it.copy(isLoading = !isRefresh, isRefreshing = isRefresh, isLoadingMore = false, error = null, loadMoreError = null)
        }

        jobs[tab] = viewModelScope.launch {
            when (val result = tasksRepository.page(tab, 1, _state.value.taskTagIds, appliedQuery)) {
                is ApiResult.Success -> updateTab(tab) {
                    TaskTabState(tasks = result.value.tasks, page = result.value.page, lastPage = result.value.lastPage)
                }
                is ApiResult.Failure -> updateTab(tab) {
                    it.copy(isLoading = false, isRefreshing = false, error = result.error)
                }
            }
        }
    }

    private fun updateTab(tab: TaskTab, transform: (TaskTabState) -> TaskTabState) {
        _state.update { state -> state.copy(tabs = state.tabs + (tab to transform(state.tabs.getValue(tab)))) }
    }

    companion object {
        const val SEARCH_DEBOUNCE_MS = 400L
    }
}
