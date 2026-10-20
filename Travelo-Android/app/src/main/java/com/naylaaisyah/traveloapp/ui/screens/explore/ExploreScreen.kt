package com.naylaaisyah.traveloapp.ui.screens.explore

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
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
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Explore lists exactly what the backend has: destinations and tour packages.
 *
 * There are no category chips because the API has no category field, no
 * "collections" with invented counts, and no notification bell pretending to
 * have messages. Search, rating, tour price and sorting all map to real
 * columns in the response.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ExploreScreen(
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onProfileClick: () -> Unit,
    onMyBookingsClick: () -> Unit,
    onHomeClick: () -> Unit,
    viewModel: ExploreViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var showFilters by remember { mutableStateOf(false) }
    val snackbarHostState = remember { SnackbarHostState() }

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
                ExploreControls(
                    filters = uiState.filters,
                    matchCount = uiState.matchCount,
                    onFilterClick = { showFilters = true },
                    onClear = {
                        viewModel.clearSearch()
                        viewModel.resetFilters()
                    }
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
                    if (uiState.filteredDestinations.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Destinations",
                                actionLabel = if (uiState.hasMorePages) "Load more" else null,
                                onAction = { viewModel.loadMoreContent() },
                                modifier = Modifier.padding(top = 8.dp)
                            )
                        }

                        items(
                            items = uiState.filteredDestinations,
                            key = { destination -> "destination-${destination.id}" }
                        ) { destination ->
                            TraveloDestinationCard(
                                destination = destination,
                                onClick = { onDestinationClick(destination.id) },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(180.dp)
                            )
                        }
                    }

                    if (uiState.filteredTourPackages.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Tour packages",
                                modifier = Modifier.padding(top = 12.dp)
                            )
                        }

                        items(
                            items = uiState.filteredTourPackages,
                            key = { tourPackage -> "tour-${tourPackage.id}" },
                            span = { GridItemSpan(maxLineSpan) }
                        ) { tourPackage ->
                            TraveloTourPackageRow(
                                tourPackage = tourPackage,
                                onClick = { onTourPackageClick(tourPackage.id) }
                            )
                        }
                    }

                    if (uiState.filteredDestinations.isEmpty() &&
                        uiState.filteredTourPackages.isEmpty() &&
                        !uiState.isLoading
                    ) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            if (!uiState.hasContent) {
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
                                    actionLabel = "Clear search & filters",
                                    onAction = {
                                        viewModel.clearSearch()
                                        viewModel.resetFilters()
                                    },
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
                "Filters & sort · ${filters.activeCount}"
            } else {
                "Filters & sort"
            },
            selected = filters.isActive,
            onClick = onFilterClick
        )

        if (filters.isActive) {
            TextButton(onClick = onClear) {
                Text(
                    text = "Clear",
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FilterSheet(
    current: ExploreFilters,
    onDismiss: () -> Unit,
    onApply: (ExploreFilters) -> Unit,
    onReset: () -> Unit
) {
    var minPrice by remember { mutableStateOf(current.minPrice?.toInt()?.toString().orEmpty()) }
    var maxPrice by remember { mutableStateOf(current.maxPrice?.toInt()?.toString().orEmpty()) }
    var minRating by remember { mutableStateOf(current.minRating) }
    var sortBy by remember { mutableStateOf(current.sortBy) }

    val minPriceValue = minPrice.toDoubleOrNull()
    val maxPriceValue = maxPrice.toDoubleOrNull()
    val priceError = minPriceValue != null &&
        maxPriceValue != null &&
        minPriceValue > maxPriceValue

    val ratingOptions: List<Pair<Double?, String>> = listOf(
        null to "Any",
        4.0 to "4.0+",
        4.5 to "4.5+"
    )

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
                text = "Filters & sort",
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )

            Spacer(Modifier.height(24.dp))

            Text(
                text = "Minimum rating",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                ratingOptions.forEach { (value, label) ->
                    TraveloChip(
                        label = label,
                        selected = minRating == value,
                        onClick = { minRating = value }
                    )
                }
            }

            Spacer(Modifier.height(24.dp))

            Text(
                text = "Tour price (Rp)",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "Only tour packages have a price.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                TraveloTextField(
                    value = minPrice,
                    onValueChange = { minPrice = it.filter(Char::isDigit) },
                    label = "Min",
                    placeholder = "0",
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    isError = priceError,
                    modifier = Modifier.weight(1f)
                )
                TraveloTextField(
                    value = maxPrice,
                    onValueChange = { maxPrice = it.filter(Char::isDigit) },
                    label = "Max",
                    placeholder = "Any",
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    isError = priceError,
                    modifier = Modifier.weight(1f)
                )
            }
            if (priceError) {
                TraveloFieldHint(
                    text = "Min price can't be higher than max.",
                    isError = true
                )
            }

            Spacer(Modifier.height(24.dp))

            Text(
                text = "Sort by",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(4.dp))

            SortOption.values().forEach { option ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 48.dp)
                        .selectable(
                            selected = sortBy == option,
                            onClick = { sortBy = option },
                            role = Role.RadioButton
                        ),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    RadioButton(
                        selected = sortBy == option,
                        onClick = null,
                        colors = RadioButtonDefaults.colors(selectedColor = Rosewood)
                    )
                    Text(
                        text = sortLabel(option),
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumInk,
                        modifier = Modifier.padding(start = 8.dp)
                    )
                }
            }

            Text(
                text = "Price sorting applies to tour packages only.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(Modifier.height(24.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                TraveloButton(
                    text = "Reset",
                    onClick = onReset,
                    variant = TraveloButtonVariant.Secondary,
                    size = TraveloButtonSize.Medium,
                    modifier = Modifier.weight(1f)
                )
                TraveloButton(
                    text = "Show results",
                    onClick = {
                        onApply(
                            ExploreFilters(
                                minPrice = minPriceValue,
                                maxPrice = maxPriceValue,
                                minRating = minRating,
                                sortBy = sortBy
                            )
                        )
                    },
                    enabled = !priceError,
                    size = TraveloButtonSize.Medium,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

private fun sortLabel(option: SortOption): String = when (option) {
    SortOption.RECOMMENDED -> "Recommended"
    SortOption.RATING -> "Top rated"
    SortOption.NEWEST -> "Newest"
    SortOption.PRICE_LOW_HIGH -> "Tour price: low to high"
    SortOption.PRICE_HIGH_LOW -> "Tour price: high to low"
}
