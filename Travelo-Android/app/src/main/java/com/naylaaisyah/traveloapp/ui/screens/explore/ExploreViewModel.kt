package com.naylaaisyah.traveloapp.ui.screens.explore

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.models.safeCity
import com.naylaaisyah.traveloapp.data.models.safeCountry
import com.naylaaisyah.traveloapp.data.models.safeDescription
import com.naylaaisyah.traveloapp.data.models.safeName
import com.naylaaisyah.traveloapp.data.models.safePrice
import com.naylaaisyah.traveloapp.data.models.safeRating
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Sort options backed by fields the API actually returns.
 *
 * Destinations have no price, so the two price options only reorder tour
 * packages; destinations keep the order the backend sent.
 */
enum class SortOption {
    RECOMMENDED,
    RATING,
    NEWEST,
    PRICE_LOW_HIGH,
    PRICE_HIGH_LOW
}

data class ExploreFilters(
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val minRating: Double? = null,
    val sortBy: SortOption = SortOption.RECOMMENDED
) {
    val activeCount: Int
        get() = listOf(minPrice, maxPrice, minRating).count { it != null } +
            if (sortBy != SortOption.RECOMMENDED) 1 else 0

    val isActive: Boolean get() = activeCount > 0
}

data class ExploreUiState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val destinations: List<Destination> = emptyList(),
    val filteredDestinations: List<Destination> = emptyList(),
    val tourPackages: List<TourPackage> = emptyList(),
    val filteredTourPackages: List<TourPackage> = emptyList(),
    val destinationMatchCount: Int = 0,
    val tourMatchCount: Int = 0,
    val searchQuery: String = "",
    val filters: ExploreFilters = ExploreFilters(),
    val error: String? = null,
    val currentPage: Int = 1,
    val hasMorePages: Boolean = false
) {
    /** True once at least one list has ever loaded, even if filters hide it. */
    val hasContent: Boolean
        get() = destinations.isNotEmpty() || tourPackages.isNotEmpty()

    val matchCount: Int
        get() = destinationMatchCount + tourMatchCount
}

@HiltViewModel
class ExploreViewModel @Inject constructor(
    private val repository: TraveloRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(ExploreUiState())
    val uiState: StateFlow<ExploreUiState> = _uiState.asStateFlow()

    private val pageSize = 10
    private var allDestinations: List<Destination> = emptyList()
    private var allTourPackages: List<TourPackage> = emptyList()

    // The full, filtered destination list. Pagination slices this list instead
    // of the unfiltered source, so "load more" can never append a destination
    // that does not match the current search/filters.
    private var filteredDestinationPool: List<Destination> = emptyList()

    init {
        loadData()
    }

    fun loadData() {
        _uiState.value = _uiState.value.copy(isLoading = true, error = null)
        loadDestinations()
        loadTourPackages()
    }

    fun refresh() {
        _uiState.value = _uiState.value.copy(isRefreshing = true, error = null)
        loadDestinations()
        loadTourPackages()
    }

    private fun loadDestinations() {
        viewModelScope.launch {
            when (val result = repository.getDestinations()) {
                is Resource.Success -> {
                    allDestinations = result.data.orEmpty()
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        isRefreshing = false,
                        destinations = allDestinations,
                        error = null
                    )
                    applyFiltersAndSort()
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        isRefreshing = false,
                        error = result.message
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    private fun loadTourPackages() {
        viewModelScope.launch {
            when (val result = repository.getTourPackages()) {
                is Resource.Success -> {
                    allTourPackages = result.data.orEmpty()
                    _uiState.value = _uiState.value.copy(
                        tourPackages = allTourPackages,
                        isRefreshing = false
                    )
                    applyFiltersAndSort()
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isRefreshing = false,
                        error = result.message
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun loadMoreContent() {
        val state = _uiState.value
        if (!state.hasMorePages) return

        val nextPage = state.currentPage + 1
        val startIndex = (nextPage - 1) * pageSize
        val endIndex = minOf(startIndex + pageSize, filteredDestinationPool.size)
        if (startIndex >= filteredDestinationPool.size) {
            _uiState.value = state.copy(hasMorePages = false)
            return
        }

        _uiState.value = state.copy(
            filteredDestinations = state.filteredDestinations +
                filteredDestinationPool.subList(startIndex, endIndex),
            currentPage = nextPage,
            hasMorePages = endIndex < filteredDestinationPool.size
        )
    }

    fun updateSearchQuery(query: String) {
        _uiState.value = _uiState.value.copy(searchQuery = query)
        applyFiltersAndSort()
    }

    fun clearSearch() {
        updateSearchQuery("")
    }

    fun applyFilters(filters: ExploreFilters) {
        _uiState.value = _uiState.value.copy(filters = filters)
        applyFiltersAndSort()
    }

    fun resetFilters() {
        applyFilters(ExploreFilters())
    }

    fun setSortOption(sortOption: SortOption) {
        applyFilters(_uiState.value.filters.copy(sortBy = sortOption))
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    /**
     * Search and filters are applied to the in-memory lists the API returned.
     * Nothing here invents a category, price or rating that the backend did not
     * provide.
     */
    private fun applyFiltersAndSort() {
        val filters = _uiState.value.filters
        val query = _uiState.value.searchQuery.trim()

        val destinations = allDestinations.filter { destination ->
            val matchesQuery = query.isBlank() ||
                destination.safeName.contains(query, ignoreCase = true) ||
                destination.safeCity.contains(query, ignoreCase = true) ||
                destination.safeCountry.contains(query, ignoreCase = true) ||
                destination.safeDescription.contains(query, ignoreCase = true)

            val matchesRating = filters.minRating == null ||
                destination.safeRating >= filters.minRating

            matchesQuery && matchesRating
        }

        val sortedDestinations = when (filters.sortBy) {
            SortOption.RATING -> destinations.sortedByDescending { it.safeRating }
            SortOption.NEWEST -> destinations.sortedByDescending { it.id }
            // Price lives on tour packages; destinations keep the API order.
            SortOption.RECOMMENDED,
            SortOption.PRICE_LOW_HIGH,
            SortOption.PRICE_HIGH_LOW -> destinations
        }

        val tours = allTourPackages.filter { tourPackage ->
            val matchesQuery = query.isBlank() ||
                tourPackage.safeTitle.contains(query, ignoreCase = true) ||
                tourPackage.safeDescription.contains(query, ignoreCase = true)

            val matchesMinPrice = filters.minPrice == null ||
                tourPackage.safePrice >= filters.minPrice

            val matchesMaxPrice = filters.maxPrice == null ||
                tourPackage.safePrice <= filters.maxPrice

            val matchesRating = filters.minRating == null ||
                tourPackage.safeRating >= filters.minRating

            matchesQuery && matchesMinPrice && matchesMaxPrice && matchesRating
        }

        val sortedTours = when (filters.sortBy) {
            SortOption.RATING -> tours.sortedByDescending { it.safeRating }
            SortOption.NEWEST -> tours.sortedByDescending { it.id }
            SortOption.PRICE_LOW_HIGH -> tours.sortedBy { it.safePrice }
            SortOption.PRICE_HIGH_LOW -> tours.sortedByDescending { it.safePrice }
            SortOption.RECOMMENDED -> tours
        }

        filteredDestinationPool = sortedDestinations

        _uiState.value = _uiState.value.copy(
            filteredDestinations = sortedDestinations.take(pageSize),
            filteredTourPackages = sortedTours,
            destinationMatchCount = sortedDestinations.size,
            tourMatchCount = sortedTours.size,
            currentPage = 1,
            hasMorePages = sortedDestinations.size > pageSize
        )
    }
}
