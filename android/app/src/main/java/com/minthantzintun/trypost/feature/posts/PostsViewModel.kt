package com.minthantzintun.trypost.feature.posts

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.minthantzintun.trypost.core.data.PostsRepository
import com.minthantzintun.trypost.core.model.Post
import com.minthantzintun.trypost.core.model.PostTab
import com.minthantzintun.trypost.core.model.SocialAccount
import com.minthantzintun.trypost.core.network.ApiError
import com.minthantzintun.trypost.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** One tab's loaded pages. `page` 0 means nothing has loaded yet. */
data class TabState(
    val posts: List<Post> = emptyList(),
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

data class PostsUiState(
    val selectedTab: PostTab = PostTab.Scheduled,
    val tabs: Map<PostTab, TabState> = PostTab.entries.associateWith { TabState() },
    /** The Social account every tab is filtered to, or null for all of them. */
    val selectedAccountId: String? = null,
    /** The filter's accounts, loaded each time the Posts screen opens. */
    val accounts: List<SocialAccount> = emptyList(),
    val isLoadingAccounts: Boolean = false,
    val accountsError: ApiError? = null,
) {
    val current: TabState get() = tabs.getValue(selectedTab)
    val selectedAccount: SocialAccount? get() = accounts.firstOrNull { it.id == selectedAccountId }
}

@HiltViewModel
class PostsViewModel @Inject constructor(
    private val postsRepository: PostsRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(PostsUiState())
    val state: StateFlow<PostsUiState> = _state.asStateFlow()

    private val jobs = mutableMapOf<PostTab, Job>()

    init {
        loadFirstPage(PostTab.Scheduled, isRefresh = false)

        viewModelScope.launch {
            postsRepository.changes.collect { reloadLoadedTabs() }
        }
    }

    fun selectTab(tab: PostTab) {
        _state.update { it.copy(selectedTab = tab) }

        val tabState = _state.value.tabs.getValue(tab)
        if (!tabState.hasLoaded && !tabState.isLoading) {
            loadFirstPage(tab, isRefresh = false)
        }
    }

    /** Loads the filter's Social accounts, keeping the last list while it does. */
    fun loadAccounts() {
        if (_state.value.isLoadingAccounts) {
            return
        }

        _state.update { it.copy(isLoadingAccounts = true, accountsError = null) }

        viewModelScope.launch {
            when (val result = postsRepository.socialAccounts()) {
                is ApiResult.Success -> _state.update { it.copy(accounts = result.value, isLoadingAccounts = false) }
                is ApiResult.Failure -> _state.update { it.copy(isLoadingAccounts = false, accountsError = result.error) }
            }
        }
    }

    /**
     * Filters every tab to [accountId], or to all accounts when it is null.
     * The loaded tabs are dropped, so each one loads again filtered.
     */
    fun selectAccount(accountId: String?) {
        if (accountId == _state.value.selectedAccountId) {
            return
        }

        jobs.values.forEach { it.cancel() }
        jobs.clear()
        _state.update { state ->
            state.copy(selectedAccountId = accountId, tabs = PostTab.entries.associateWith { TabState() })
        }
        loadFirstPage(_state.value.selectedTab, isRefresh = false)
    }

    /** Pull to refresh: reloads the first page of the selected tab. */
    fun refresh() {
        loadFirstPage(_state.value.selectedTab, isRefresh = true)
    }

    /** Reloads every tab that has loaded, e.g. after a Post changed elsewhere. */
    fun reloadLoadedTabs() {
        _state.value.tabs
            .filterValues { it.hasLoaded }
            .keys
            .forEach { loadFirstPage(it, isRefresh = true) }
    }

    fun retry() {
        loadFirstPage(_state.value.selectedTab, isRefresh = false)
    }

    fun loadMore() {
        val tab = _state.value.selectedTab
        val tabState = _state.value.tabs.getValue(tab)
        if (!tabState.hasMore || tabState.isLoadingMore || tabState.isLoading || tabState.isRefreshing) {
            return
        }

        updateTab(tab) { it.copy(isLoadingMore = true, loadMoreError = null) }

        jobs[tab] = viewModelScope.launch {
            when (val result = postsRepository.page(tab, tabState.page + 1, _state.value.selectedAccountId)) {
                is ApiResult.Success -> updateTab(tab) { current ->
                    val knownIds = current.posts.mapTo(HashSet()) { it.id }
                    current.copy(
                        posts = current.posts + result.value.posts.filterNot { it.id in knownIds },
                        page = result.value.page,
                        lastPage = result.value.lastPage,
                        isLoadingMore = false,
                    )
                }
                is ApiResult.Failure -> updateTab(tab) { it.copy(isLoadingMore = false, loadMoreError = result.error) }
            }
        }
    }

    private fun loadFirstPage(tab: PostTab, isRefresh: Boolean) {
        jobs[tab]?.cancel()
        updateTab(tab) {
            it.copy(isLoading = !isRefresh, isRefreshing = isRefresh, isLoadingMore = false, error = null, loadMoreError = null)
        }

        jobs[tab] = viewModelScope.launch {
            when (val result = postsRepository.page(tab, 1, _state.value.selectedAccountId)) {
                is ApiResult.Success -> updateTab(tab) {
                    TabState(posts = result.value.posts, page = result.value.page, lastPage = result.value.lastPage)
                }
                is ApiResult.Failure -> updateTab(tab) {
                    it.copy(isLoading = false, isRefreshing = false, error = result.error)
                }
            }
        }
    }

    private fun updateTab(tab: PostTab, transform: (TabState) -> TabState) {
        _state.update { state -> state.copy(tabs = state.tabs + (tab to transform(state.tabs.getValue(tab)))) }
    }
}
