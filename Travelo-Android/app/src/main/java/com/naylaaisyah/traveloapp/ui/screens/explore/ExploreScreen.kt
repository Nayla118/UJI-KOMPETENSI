package com.naylaaisyah.traveloapp.ui.screens.explore

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.grid.rememberLazyGridState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.RangeSlider
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.data.models.WishlistTypes
import com.naylaaisyah.traveloapp.data.wishlist.WishlistKey
import com.naylaaisyah.traveloapp.ui.components.TraveloBottomNav
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloChip
import com.naylaaisyah.traveloapp.ui.components.TraveloDestinationCard
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloFieldHint
import com.naylaaisyah.traveloapp.ui.components.TraveloInlineLoading
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloSearchField
import com.naylaaisyah.traveloapp.ui.components.TraveloSectionHeader
import com.naylaaisyah.traveloapp.ui.components.TraveloTextField
import com.naylaaisyah.traveloapp.ui.components.TraveloTourPackageRow
import com.naylaaisyah.traveloapp.ui.screens.favorites.WishlistEvent
import com.naylaaisyah.traveloapp.ui.screens.favorites.WishlistViewModel
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import kotlinx.coroutines.flow.distinctUntilChanged

/**
 * Explore reads the server-paginated catalog. Search, filters, sorting and
 * paging are all part of the API request; nothing is filtered client-side.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ExploreScreen(
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onProfileClick: () -> Unit,
    onMyBookingsClick: () -> Unit,
    onHomeClick: () -> Unit,
    onRequireLogin: () -> Unit = {},
    viewModel: ExploreViewModel = hiltViewModel(),
    wishlistViewModel: WishlistViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val wishlistState by wishlistViewModel.toggleState.collectAsState()
    var showFilters by remember { mutableStateOf(false) }
    val snackbarHostState = remember { SnackbarHostState() }
    val gridState = rememberLazyGridState()

    val showInitialLoading = uiState.isLoading && !uiState.hasContent
    val showError = uiState.error != null && !uiState.hasContent

    // A failed refresh must not disappear just because content is still on
    // screen from the previous load.
    LaunchedEffect(uiState.error, uiState.hasContent) {
        val message = uiState.error
        if (message != null && uiState.hasContent) {
            snackbarHostState.showSnackbar(message)
            viewModel.clearError()
        }
    }

    LaunchedEffect(uiState.loadMoreError) {
        uiState.loadMoreError?.let { snackbarHostState.showSnackbar(it) }
    }

    LaunchedEffect(wishlistViewModel) {
        wishlistViewModel.events.collect { event ->
            when (event) {
                is WishlistEvent.RequiresLogin -> onRequireLogin()
                is WishlistEvent.Message -> snackbarHostState.showSnackbar(event.text)
            }
        }
    }

    // Infinite scroll: request the next page when the last few items become
    // visible. The ViewModel ignores duplicate calls while a page is loading.
    LaunchedEffect(gridState) {
        snapshotFlow {
            val info = gridState.layoutInfo
            (info.visibleItemsInfo.lastOrNull()?.index ?: -1) to info.totalItemsCount
        }
            .distinctUntilChanged()
            .collect { (lastVisible, totalItems) ->
                if (totalItems > 0 && lastVisible >= totalItems - 3) {
                    viewModel.loadMore()
                }
            }
    }

    Scaffold(
        containerColor = VanillaCream,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        bottomBar = {
            TraveloBottomNav(
                currentRoute = "explore",
                onSelect = { route ->
                    when (route) {
                        "home" -> onHomeClick()
                        "my_bookings" -> onMyBookingsClick()
                        "profile" -> onProfileClick()
                    }
                }
            )
        }
    ) { innerPadding ->
        LazyVerticalGrid(
            columns = GridCells.Adaptive(minSize = 150.dp),
            state = gridState,
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding),
            contentPadding = PaddingValues(
                start = TraveloSpacing.screen,
                end = TraveloSpacing.screen,
                top = 8.dp,
                bottom = TraveloSpacing.lg
            ),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item(span = { GridItemSpan(maxLineSpan) }) {
                Column(modifier = Modifier.padding(top = 12.dp, bottom = 4.dp)) {
                    Text(
                        text = "Explore",
                        style = MaterialTheme.typography.displaySmall,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = "Every destination and tour package currently available.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumMuted
                    )
                }
            }

            item(span = { GridItemSpan(maxLineSpan) }) {
                TraveloSearchField(
                    value = uiState.searchQuery,
                    onValueChange = viewModel::updateSearchQuery,
                    placeholder = "Search destinations or tour packages"
                )
            }

            item(span = { GridItemSpan(maxLineSpan) }) {
                SortChips(
                    selected = uiState.filters.sortBy,
                    onSelect = viewModel::setSortOption
                )
            }

            item(span = { GridItemSpan(maxLineSpan) }) {
                ExploreControls(
                    filters = uiState.filters,
                    matchCount = uiState.matchCount,
                    onFilterClick = { showFilters = true },
                    onClear = viewModel::clearSearchAndFilters
                )
            }

            if (uiState.isRefreshing) {
                item(span = { GridItemSpan(maxLineSpan) }) {
                    TraveloInlineLoading()
                }
            }

            when {
                showInitialLoading -> {
                    item(span = { GridItemSpan(maxLineSpan) }) {
                        TraveloLoadingState(modifier = Modifier.padding(top = 8.dp))
                    }
                }

                showError -> {
                    item(span = { GridItemSpan(maxLineSpan) }) {
                        TraveloErrorState(
                            message = uiState.error.orEmpty(),
                            onRetry = viewModel::refresh,
                            title = "Couldn't load Explore",
                            horizontalPadding = 4.dp
                        )
                    }
                }

                else -> {
                    if (uiState.destinations.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Destinations",
                                modifier = Modifier.padding(top = 8.dp)
                            )
                        }

                        items(
                            items = uiState.destinations,
                            key = { destination -> "destination-${destination.id}" }
                        ) { destination ->
                            TraveloDestinationCard(
                                destination = destination,
                                onClick = { onDestinationClick(destination.id) },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(180.dp),
                                isWishlisted = wishlistState.isWishlisted(
                                    WishlistKey(WishlistTypes.DESTINATION, destination.id),
                                    destination.isWishlisted
                                ),
                                onToggleWishlist = {
                                    wishlistViewModel.toggle(
                                        WishlistTypes.DESTINATION,
                                        destination.id,
                                        destination.isWishlisted
                                    )
                                }
                            )
                        }
                    }

                    if (uiState.tourPackages.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Tour packages",
                                modifier = Modifier.padding(top = 12.dp)
                            )
                        }

                        items(
                            items = uiState.tourPackages,
                            key = { tourPackage -> "tour-${tourPackage.id}" },
                            span = { GridItemSpan(maxLineSpan) }
                        ) { tourPackage ->
                            TraveloTourPackageRow(
                                tourPackage = tourPackage,
                                onClick = { onTourPackageClick(tourPackage.id) },
                                isWishlisted = wishlistState.isWishlisted(
                                    WishlistKey(WishlistTypes.TOUR_PACKAGE, tourPackage.id),
                                    tourPackage.isWishlisted
                                ),
                                onToggleWishlist = {
                                    wishlistViewModel.toggle(
                                        WishlistTypes.TOUR_PACKAGE,
                                        tourPackage.id,
                                        tourPackage.isWishlisted
                                    )
                                }
                            )
                        }
                    }

                    when {
                        uiState.isLoadingMore -> {
                            item(span = { GridItemSpan(maxLineSpan) }) {
                                TraveloInlineLoading()
                            }
                        }

                        uiState.loadMoreError != null -> {
                            item(span = { GridItemSpan(maxLineSpan) }) {
                                LoadMoreError(
                                    message = uiState.loadMoreError.orEmpty(),
                                    onRetry = viewModel::retryLoadMore
                                )
                            }
                        }

                        uiState.hasMorePages -> {
                            item(span = { GridItemSpan(maxLineSpan) }) {
                                TextButton(onClick = viewModel::loadMore) {
                                    Text(
                                        text = "Load more",
                                        style = MaterialTheme.typography.labelLarge,
                                        color = Rosewood
                                    )
                                }
                            }
                        }
                    }

                    if (uiState.destinations.isEmpty() &&
                        uiState.tourPackages.isEmpty() &&
                        !uiState.isLoading &&
                        !uiState.isRefreshing
                    ) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            val hasQueryOrFilters = uiState.searchQuery.isNotBlank() ||
                                uiState.filters.isActive

                            if (!hasQueryOrFilters) {
                                TraveloEmptyState(
                                    title = "Nothing to explore yet",
                                    message = "There are no destinations or tour packages available right now.",
                                    actionLabel = "Refresh",
                                    onAction = viewModel::refresh,
                                    horizontalPadding = 4.dp
                                )
                            } else {
                                TraveloEmptyState(
                                    title = "No matches",
                                    message = if (uiState.searchQuery.isNotBlank()) {
                                        "No destination or tour package matches \"${uiState.searchQuery}\"."
                                    } else {
                                        "Nothing matches these filters. Try widening them."
                                    },
                                    actionLabel = "Reset search & filters",
                                    onAction = viewModel::clearSearchAndFilters,
                                    horizontalPadding = 4.dp
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (showFilters) {
        FilterSheet(
            current = uiState.filters,
            onDismiss = { showFilters = false },
            onApply = { filters ->
                viewModel.applyFilters(filters)
                showFilters = false
            },
            onReset = {
                viewModel.resetFilters()
                showFilters = false
            }
        )
    }
}

@Composable
private fun SortChips(
    selected: SortOption,
    onSelect: (SortOption) -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        SortOption.values().forEach { option ->
            TraveloChip(
                label = option.label,
                selected = selected == option,
                onClick = { onSelect(option) }
            )
        }
    }
}

@Composable
private fun ExploreControls(
    filters: ExploreFilters,
    matchCount: Int,
    onFilterClick: () -> Unit,
    onClear: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        TraveloChip(
            label = if (filters.activeCount > 0) {
                "Filters · ${filters.activeCount}"
            } else {
                "Filters"
            },
            selected = filters.isActive,
            onClick = onFilterClick
        )

        if (filters.isActive) {
            TextButton(onClick = onClear) {
                Text(
                    text = "Reset",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }

        Spacer(Modifier.weight(1f))

        Text(
            text = if (matchCount == 1) "1 result" else "$matchCount results",
            style = MaterialTheme.typography.labelMedium,
            color = PlumMuted
        )
    }
}

@Composable
private fun LoadMoreError(message: String, onRetry: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = message,
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
        TextButton(onClick = onRetry) {
            Text(
                text = "Try again",
                style = MaterialTheme.typography.labelLarge,
                color = Rosewood
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FilterSheet(
    current: ExploreFilters,
    onDismiss: () -> Unit,
    onApply: (ExploreFilters) -> Unit,
    onReset: () -> Unit
) {
    var category by remember { mutableStateOf(current.category) }
    var priceRange by remember {
        mutableStateOf(
            (current.minPrice?.toFloat() ?: 0f)..
                (current.maxPrice?.toFloat() ?: ExploreFilters.MAX_PRICE.toFloat())
        )
    }
    var minRating by remember { mutableStateOf(current.minRating) }
    var duration by remember { mutableStateOf(current.duration) }
    var availableDate by remember { mutableStateOf(current.availableDate.orEmpty()) }

    val dateValid = availableDate.isBlank() ||
        REGEX_DATE.matches(availableDate.trim())

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = VanillaCream
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(horizontal = TraveloSpacing.screen)
                .padding(bottom = 24.dp)
        ) {
            Text(
                text = "Filters",
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )

            Spacer(Modifier.height(20.dp))

            Text(
                text = "Category",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                TraveloChip(
                    label = "Any",
                    selected = category == null,
                    onClick = { category = null }
                )
                ExploreFilters.CATEGORIES.forEach { value ->
                    TraveloChip(
                        label = ExploreFilters.categoryLabel(value),
                        selected = category == value,
                        onClick = { category = value }
                    )
                }
            }

            Spacer(Modifier.height(20.dp))

            Text(
                text = "Price range",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = "${rupiah(priceRange.start)} – ${rupiah(priceRange.endInclusive)}",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
            RangeSlider(
                value = priceRange,
                onValueChange = { priceRange = it },
                valueRange = 0f..ExploreFilters.MAX_PRICE.toFloat()
            )

            Spacer(Modifier.height(8.dp))

            Text(
                text = "Minimum rating",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TraveloChip(
                    label = "Any",
                    selected = minRating == null,
                    onClick = { minRating = null }
                )
                ExploreFilters.RATINGS.forEach { value ->
                    TraveloChip(
                        label = "${value.toString().removeSuffix(".0")}+",
                        selected = minRating == value,
                        onClick = { minRating = value }
                    )
                }
            }

            Spacer(Modifier.height(20.dp))

            Text(
                text = "Duration",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                TraveloChip(
                    label = "Any",
                    selected = duration == null,
                    onClick = { duration = null }
                )
                ExploreFilters.DURATIONS.forEach { value ->
                    TraveloChip(
                        label = ExploreFilters.durationLabel(value),
                        selected = duration == value,
                        onClick = { duration = value }
                    )
                }
            }

            Spacer(Modifier.height(20.dp))

            Text(
                text = "Departure date",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(8.dp))
            TraveloTextField(
                value = availableDate,
                onValueChange = { value ->
                    availableDate = value
                        .filter { it.isDigit() || it == '-' }
                        .take(10)
                },
                label = "Available on",
                placeholder = "YYYY-MM-DD",
                isError = !dateValid
            )
            TraveloFieldHint(
                text = if (dateValid) {
                    "Only packages with seats left on this date are shown."
                } else {
                    "Use the YYYY-MM-DD format, for example 2026-11-20."
                },
                isError = !dateValid
            )

            Spacer(Modifier.height(24.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                TraveloButton(
                    text = "Reset filter",
                    onClick = onReset,
                    variant = TraveloButtonVariant.Secondary,
                    size = TraveloButtonSize.Medium,
                    modifier = Modifier.weight(1f)
                )
                TraveloButton(
                    text = "Show results",
                    onClick = {
                        val start = priceRange.start
                        val end = priceRange.endInclusive
                        val maxPrice = ExploreFilters.MAX_PRICE.toFloat()

                        onApply(
                            ExploreFilters(
                                category = category,
                                minPrice = start.takeIf { it > 0f }?.toDouble(),
                                maxPrice = end.takeIf { it < maxPrice - 1f }?.toDouble(),
                                minRating = minRating,
                                duration = duration,
                                availableDate = availableDate.trim().takeIf {
                                    it.isNotBlank()
                                },
                                // Sorting lives on the Explore screen, so
                                // applying filters must not reset it.
                                sortBy = current.sortBy
                            )
                        )
                    },
                    enabled = dateValid,
                    size = TraveloButtonSize.Medium,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

private val REGEX_DATE = Regex("""\d{4}-\d{2}-\d{2}""")

private fun rupiah(value: Float): String =
    "Rp " + String.format(java.util.Locale.US, "%,d", value.toLong()).replace(',', '.')
