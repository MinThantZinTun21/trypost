package com.minthantzintun.trypost.core.ui

import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.staggeredgrid.LazyStaggeredGridState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember

private const val LOAD_MORE_THRESHOLD = 5

/**
 * Infinite scroll: asks for the next page once the list is within a few rows
 * of its end. After a failed page it waits for the Owner's retry.
 */
@Composable
fun LoadMoreWhenNearEnd(
    listState: LazyListState,
    hasMore: Boolean,
    page: Int,
    hasLoadMoreError: Boolean,
    onLoadMore: () -> Unit,
) {
    val isNearEnd by remember(listState) {
        derivedStateOf {
            val lastVisible = listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0
            lastVisible >= listState.layoutInfo.totalItemsCount - LOAD_MORE_THRESHOLD
        }
    }

    LoadMoreWhen(isNearEnd, hasMore, page, hasLoadMoreError, onLoadMore)
}

/** [LoadMoreWhenNearEnd] for a staggered grid, such as the Tasks cards. */
@Composable
fun LoadMoreWhenNearEnd(
    gridState: LazyStaggeredGridState,
    hasMore: Boolean,
    page: Int,
    hasLoadMoreError: Boolean,
    onLoadMore: () -> Unit,
) {
    val isNearEnd by remember(gridState) {
        derivedStateOf {
            val lastVisible = gridState.layoutInfo.visibleItemsInfo.maxOfOrNull { it.index } ?: 0
            lastVisible >= gridState.layoutInfo.totalItemsCount - LOAD_MORE_THRESHOLD
        }
    }

    LoadMoreWhen(isNearEnd, hasMore, page, hasLoadMoreError, onLoadMore)
}

@Composable
private fun LoadMoreWhen(isNearEnd: Boolean, hasMore: Boolean, page: Int, hasLoadMoreError: Boolean, onLoadMore: () -> Unit) {
    LaunchedEffect(isNearEnd, hasMore, page) {
        if (isNearEnd && hasMore && !hasLoadMoreError) {
            onLoadMore()
        }
    }
}
