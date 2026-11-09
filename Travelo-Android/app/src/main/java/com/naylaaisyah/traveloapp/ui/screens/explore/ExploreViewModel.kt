package com.naylaaisyah.traveloapp.ui.screens.explore

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.CatalogPage
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ExploreUiState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val destinations: List<Destination> = emptyList(),
    val tourPackages: List<TourPackage> = emptyList(),
    val destinationTotal: Int = 0,
    val tourTotal: Int = 0,
    val searchQuery: String = "",
    val filters: ExploreFilters = ExploreFilters(),
    val error: String? = null,
    val loadMoreError: String? = null,
    val destinationsPage: Int = 1,
    val destinationsLastPage: Int = 1,
    val tourPackagesPage: Int = 1,
    val tourPackagesLastPage: Int = 1
) {
    /** True once at least one list has ever loaded, even if filters hide it. */
    val hasContent: Boolean
        get() = destinations.isNotEmpty() || tourPackages.isNotEmpty()

    val hasMorePages: Boolean
        get() = destinationsPage < destinationsLastPage ||
            tourPackagesPage < tourPackagesLastPage

    val matchCount: Int
        get() = destinationTotal + tourTotal
}

/**
 * Server-driven Explore catalog.
 *
 * Every filter, sort and page is resolved by the backend; the app never
 * filters a single fetched page client-side. Search is debounced (350 ms),
 * both lists load in parallel, and page appends are guarded by a request
 * version so a slow response can never overwrite a newer filter.
 */
@HiltViewModel
class ExploreViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val savedStateHandle: SavedStateHandle
) : ViewModel() {

    private val _uiState = MutableStateFlow(ExploreUiState())
    val uiState: StateFlow<ExploreUiState> = _uiState.asStateFlow()

    private var requestVersion = 0
    private var searchJob: Job? = null

    init {
        val restoredQuery = savedStateHandle.get<String>(KEY_QUERY).orEmpty()
        val restoredFilters = restoreFilters()

        _uiState.value = ExploreUiState(
            searchQuery = restoredQuery,
            filters = restoredFilters
        )

        loadFirstPage()
    }

    // ---------- Loading ----------

    /**
     * Loads page 1 of destinations and tour packages with the same query.
     * Both requests run concurrently; an error on one list does not discard
     * the other list's data.
     */
    private fun loadFirstPage(refreshing: Boolean = false) {
        val version = ++requestVersion
        val state = _uiState.value
        val query = state.filters.toCatalogQuery(
            searchQuery = state.searchQuery,
            page = 1,
            perPage = PAGE_SIZE
        )

        _uiState.value = state.copy(
            isLoading = !refreshing && !state.hasContent,
            isRefreshing = refreshing,
            error = null,
            loadMoreError = null
        )

        viewModelScope.launch {
            val destinationRequest = async { repository.getDestinations(query) }
            val tourRequest = async { repository.getTourPackages(query) }

            val destinationResult = destinationRequest.await()
            val tourResult = tourRequest.await()

            if (version != requestVersion) return@launch

            applyFirstPage(destinationResult, tourResult)
        }
    }

    private fun applyFirstPage(
        destinationResult: Resource<CatalogPage<Destination>>,
        tourResult: Resource<CatalogPage<TourPackage>>
    ) {
        val destinationPage = (destinationResult as? Resource.Success)?.data
        val tourPage = (tourResult as? Resource.Success)?.data
        val error = (destinationResult as? Resource.Error)?.message?.takeIf { it.isNotBlank() }
            ?: (tourResult as? Resource.Error)?.message?.takeIf { it.isNotBlank() }

        val current = _uiState.value

        _uiState.value = current.copy(
            isLoading = false,
            isRefreshing = false,
            // Keep the previous rows for a list that failed during refresh;
            // a first load simply starts empty.
            destinations = destinationPage?.items ?: current.destinations,
            tourPackages = tourPage?.items ?: current.tourPackages,
            destinationTotal = destinationPage?.meta?.total ?: current.destinationTotal,
            tourTotal = tourPage?.meta?.total ?: current.tourTotal,
            destinationsPage = destinationPage?.meta?.currentPage ?: current.destinationsPage,
            destinationsLastPage = destinationPage?.meta?.lastPage ?: current.destinationsLastPage,
            tourPackagesPage = tourPage?.meta?.currentPage ?: current.tourPackagesPage,
            tourPackagesLastPage = tourPage?.meta?.lastPage ?: current.tourPackagesLastPage,
            error = error
        )
    }

    fun refresh() {
        searchJob?.cancel()
        loadFirstPage(refreshing = true)
    }

    /**
     * Appends the next page for whichever lists still have one. Triggered by
     * the grid nearing its end; safe to call repeatedly.
     */
    fun loadMore() {
        val state = _uiState.value
        if (state.isLoading || state.isRefreshing || state.isLoadingMore) return
        if (!state.hasMorePages) return

        val nextDestinationPage = state.destinationsPage
            .takeIf { it < state.destinationsLastPage }
            ?.plus(1)
        val nextTourPage = state.tourPackagesPage
            .takeIf { it < state.tourPackagesLastPage }
            ?.plus(1)

        if (nextDestinationPage == null && nextTourPage == null) return

        val version = requestVersion
        val destinationQuery = state.filters.toCatalogQuery(
            state.searchQuery,
            page = nextDestinationPage ?: state.destinationsPage,
            perPage = PAGE_SIZE
        )
        val tourQuery = state.filters.toCatalogQuery(
            state.searchQuery,
            page = nextTourPage ?: state.tourPackagesPage,
            perPage = PAGE_SIZE
        )

        _uiState.value = state.copy(isLoadingMore = true, loadMoreError = null)

        viewModelScope.launch {
            var destinationPage: CatalogPage<Destination>? = null
            var tourPage: CatalogPage<TourPackage>? = null
            var error: String? = null

            if (nextDestinationPage != null) {
                when (val result = repository.getDestinations(destinationQuery)) {
                    is Resource.Success -> destinationPage = result.data
                    is Resource.Error -> error = result.message
                    is Resource.Loading -> Unit
                }
            }

            if (nextTourPage != null) {
                when (val result = repository.getTourPackages(tourQuery)) {
                    is Resource.Success -> tourPage = result.data
                    is Resource.Error -> error = error ?: result.message
                    is Resource.Loading -> Unit
                }
            }

            if (version != requestVersion) return@launch

            val current = _uiState.value

            _uiState.value = current.copy(
                destinations = appendDistinct(
                    current.destinations,
                    destinationPage?.items.orEmpty()
                ),
                tourPackages = appendDistinct(
                    current.tourPackages,
                    tourPage?.items.orEmpty()
                ),
                destinationsPage = destinationPage?.meta?.currentPage
                    ?: current.destinationsPage,
                destinationsLastPage = destinationPage?.meta?.lastPage
                    ?: current.destinationsLastPage,
                tourPackagesPage = tourPage?.meta?.currentPage
                    ?: current.tourPackagesPage,
                tourPackagesLastPage = tourPage?.meta?.lastPage
                    ?: current.tourPackagesLastPage,
                destinationTotal = destinationPage?.meta?.total
                    ?: current.destinationTotal,
                tourTotal = tourPage?.meta?.total ?: current.tourTotal,
                isLoadingMore = false,
                loadMoreError = error?.takeIf { it.isNotBlank() }
            )
        }
    }

    fun retryLoadMore() {
        _uiState.value = _uiState.value.copy(loadMoreError = null)
        loadMore()
    }

    // ---------- Search and filters ----------

    fun updateSearchQuery(query: String) {
        val normalised = query.take(MAX_QUERY_LENGTH)
        _uiState.value = _uiState.value.copy(searchQuery = normalised)
        savedStateHandle[KEY_QUERY] = normalised

        // Debounce keystrokes: only the last query within 350 ms hits the API.
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MS)
            loadFirstPage()
        }
    }

    fun clearSearch() {
        updateSearchQuery("")
    }

    fun applyFilters(filters: ExploreFilters) {
        searchJob?.cancel()
        _uiState.value = _uiState.value.copy(filters = filters)
        persistFilters(filters)
        // Filters/sort are an explicit action, so show the inline progress row
        // even when the previous page is still on screen.
        loadFirstPage(refreshing = _uiState.value.hasContent)
    }

    fun resetFilters() {
        applyFilters(ExploreFilters())
    }

    fun setSortOption(sortOption: SortOption) {
        val current = _uiState.value.filters
        if (current.sortBy == sortOption) return
        applyFilters(current.copy(sortBy = sortOption))
    }

    fun clearSearchAndFilters() {
        searchJob?.cancel()
        _uiState.value = _uiState.value.copy(
            searchQuery = "",
            filters = ExploreFilters()
        )
        savedStateHandle[KEY_QUERY] = ""
        persistFilters(ExploreFilters())
        loadFirstPage()
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    fun clearLoadMoreError() {
        _uiState.value = _uiState.value.copy(loadMoreError = null)
    }

    // ---------- Saved state ----------

    private fun restoreFilters(): ExploreFilters = ExploreFilters(
        category = savedStateHandle[KEY_CATEGORY],
        minPrice = savedStateHandle[KEY_MIN_PRICE],
        maxPrice = savedStateHandle[KEY_MAX_PRICE],
        minRating = savedStateHandle[KEY_MIN_RATING],
        duration = savedStateHandle[KEY_DURATION],
        availableDate = savedStateHandle[KEY_AVAILABLE_DATE],
        sortBy = savedStateHandle.get<String>(KEY_SORT)
            ?.let { name -> SortOption.values().firstOrNull { it.name == name } }
            ?: SortOption.RECOMMENDED
    )

    private fun persistFilters(filters: ExploreFilters) {
        savedStateHandle[KEY_CATEGORY] = filters.category
        savedStateHandle[KEY_MIN_PRICE] = filters.minPrice
        savedStateHandle[KEY_MAX_PRICE] = filters.maxPrice
        savedStateHandle[KEY_MIN_RATING] = filters.minRating
        savedStateHandle[KEY_DURATION] = filters.duration
        savedStateHandle[KEY_AVAILABLE_DATE] = filters.availableDate
        savedStateHandle[KEY_SORT] = filters.sortBy.name
    }

    private fun <T> appendDistinct(existing: List<T>, incoming: List<T>): List<T> =
        (existing + incoming).distinct()

    companion object {
        const val PAGE_SIZE = 10
        private const val SEARCH_DEBOUNCE_MS = 350L
        private const val MAX_QUERY_LENGTH = 255

        private const val KEY_QUERY = "explore_query"
        private const val KEY_CATEGORY = "explore_category"
        private const val KEY_MIN_PRICE = "explore_min_price"
        private const val KEY_MAX_PRICE = "explore_max_price"
        private const val KEY_MIN_RATING = "explore_min_rating"
        private const val KEY_DURATION = "explore_duration"
        private const val KEY_AVAILABLE_DATE = "explore_available_date"
        private const val KEY_SORT = "explore_sort"
    }
}
