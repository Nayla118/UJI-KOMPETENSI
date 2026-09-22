package com.naylaaisyah.traveloapp.ui.screens.explore

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.safeCity
import com.naylaaisyah.traveloapp.data.models.safeCountry
import com.naylaaisyah.traveloapp.data.models.safeDescription
import com.naylaaisyah.traveloapp.data.models.safeName
import com.naylaaisyah.traveloapp.data.models.safePrice
import com.naylaaisyah.traveloapp.data.models.safeRating
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

// Sort options
enum class SortOption {
    PRICE_LOW_HIGH,
    PRICE_HIGH_LOW,
    NEWEST,
    RATING
}

// View mode
enum class ViewMode {
    GRID,
    LIST
}

// Filter data class
data class ExploreFilters(
    val category: String? = null,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val minRating: Float? = null,
    val sortBy: SortOption = SortOption.RATING
)

data class ExploreUiState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val destinations: List<Destination> = emptyList(),
    val filteredDestinations: List<Destination> = emptyList(),
    val tourPackages: List<TourPackage> = emptyList(),
    val filteredTourPackages: List<TourPackage> = emptyList(),
    val searchQuery: String = "",
    val selectedCategory: String? = null,
    val filters: ExploreFilters = ExploreFilters(),
    val viewMode: ViewMode = ViewMode.GRID,
    val error: String? = null,
    val userName: String = "",
    val userPhoto: String? = null,
    val favoriteDestinationIds: Set<Int> = emptySet(),
    val favoriteTourIds: Set<Int> = emptySet(),
    val currentPage: Int = 1,
    val hasMorePages: Boolean = true,
    val isSearchActive: Boolean = false,
    val searchResults: List<Destination> = emptyList(),
    val searchTourResults: List<TourPackage> = emptyList()
)

@HiltViewModel
class ExploreViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {
    
    private val _uiState = MutableStateFlow(ExploreUiState())
    val uiState: StateFlow<ExploreUiState> = _uiState.asStateFlow()
    
    // Pagination
    private val pageSize = 10
    private var allDestinations: List<Destination> = emptyList()
    private var allTourPackages: List<TourPackage> = emptyList()
    
    init {
        loadData()
        loadUserInfo()
        loadFavorites()
    }
    
    private fun loadUserInfo() {
        val name = sessionManager.userName.orEmpty()
        val photo = sessionManager.userPhoto
        _uiState.value = _uiState.value.copy(
            userName = name,
            userPhoto = photo
        )
    }
    
    private fun loadFavorites() {
        // Load favorites from local storage or API
        // For now, using empty set - would integrate with local database
    }
    
    fun loadData() {
        loadDestinations()
        loadTourPackages()
    }
    
    fun refresh() {
        _uiState.value = _uiState.value.copy(
            isRefreshing = true,
            currentPage = 1,
            hasMorePages = true
        )
        loadDestinations()
        loadTourPackages()
    }
    
    private fun loadDestinations() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)

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
        if (_uiState.value.isLoadingMore || !_uiState.value.hasMorePages) return
        
        _uiState.value = _uiState.value.copy(isLoadingMore = true)
        
        // Simulate pagination - in real app would call API with page parameter
        val currentPage = _uiState.value.currentPage + 1
        val startIndex = (currentPage - 1) * pageSize
        
        if (startIndex >= allDestinations.size) {
            _uiState.value = _uiState.value.copy(
                isLoadingMore = false,
                hasMorePages = false
            )
            return
        }
        
        val endIndex = minOf(startIndex + pageSize, allDestinations.size)
        val newDestinations = allDestinations.subList(startIndex, endIndex)
        
        _uiState.value = _uiState.value.copy(
            isLoadingMore = false,
            currentPage = currentPage,
            hasMorePages = endIndex < allDestinations.size,
            filteredDestinations = _uiState.value.filteredDestinations + newDestinations
        )
    }
    
    // Search functionality
    fun updateSearchQuery(query: String) {
        _uiState.value = _uiState.value.copy(
            searchQuery = query,
            isSearchActive = query.isNotBlank()
        )
        
        if (query.isBlank()) {
            _uiState.value = _uiState.value.copy(
                searchResults = emptyList(),
                searchTourResults = emptyList()
            )
            return
        }
        
        // Filter destinations by search query
        val filteredDestinations = allDestinations.filter { destination ->
            destination.safeName.contains(query, ignoreCase = true) ||
            destination.safeCity.contains(query, ignoreCase = true) ||
            destination.safeCountry.contains(query, ignoreCase = true) ||
            destination.safeDescription.contains(query, ignoreCase = true)
        }
        
        // Filter tour packages by search query
        val filteredTours = allTourPackages.filter { tourPackage ->
            tourPackage.safeTitle.contains(query, ignoreCase = true) ||
            tourPackage.safeDescription.contains(query, ignoreCase = true)
        }
        
        _uiState.value = _uiState.value.copy(
            searchResults = filteredDestinations,
            searchTourResults = filteredTours
        )
    }
    
    fun clearSearch() {
        _uiState.value = _uiState.value.copy(
            searchQuery = "",
            isSearchActive = false,
            searchResults = emptyList(),
            searchTourResults = emptyList()
        )
    }
    
    // Category filtering
    fun filterByCategory(category: String?) {
        _uiState.value = _uiState.value.copy(
            selectedCategory = category,
            filters = _uiState.value.filters.copy(category = category)
        )
        applyFiltersAndSort()
    }
    
    // Apply filters
    fun applyFilters(filters: ExploreFilters) {
        _uiState.value = _uiState.value.copy(
            filters = filters,
            selectedCategory = filters.category
        )
        applyFiltersAndSort()
    }
    
    // Reset filters
    fun resetFilters() {
        _uiState.value = _uiState.value.copy(
            filters = ExploreFilters(),
            selectedCategory = null
        )
        applyFiltersAndSort()
    }
    
    // Sort functionality
    fun setSortOption(sortOption: SortOption) {
        _uiState.value = _uiState.value.copy(
            filters = _uiState.value.filters.copy(sortBy = sortOption)
        )
        applyFiltersAndSort()
    }
    
    // View mode toggle
    fun toggleViewMode() {
        val newMode = if (_uiState.value.viewMode == ViewMode.GRID) {
            ViewMode.LIST
        } else {
            ViewMode.GRID
        }
        _uiState.value = _uiState.value.copy(viewMode = newMode)
    }
    
    fun setViewMode(mode: ViewMode) {
        _uiState.value = _uiState.value.copy(viewMode = mode)
    }
    
    // Apply filters and sorting
    private fun applyFiltersAndSort() {
        val filters = _uiState.value.filters
        
        // Filter destinations
        var filtered = allDestinations.filter { destination ->
            val matchesCategory = filters.category == null || 
                filters.category == "All" ||
                destination.safeName.contains(filters.category!!, ignoreCase = true)
            
            val matchesRating = filters.minRating == null || 
                destination.safeRating >= filters.minRating
            
            matchesCategory && matchesRating
        }
        
        // Sort destinations
        filtered = when (filters.sortBy) {
            SortOption.PRICE_LOW_HIGH -> filtered.sortedBy { it.safeRating }
            SortOption.PRICE_HIGH_LOW -> filtered.sortedByDescending { it.safeRating }
            SortOption.NEWEST -> filtered.sortedByDescending { it.id }
            SortOption.RATING -> filtered.sortedByDescending { it.safeRating }
        }
        
        // Filter tour packages
        var filteredTours = allTourPackages.filter { tourPackage ->
            val matchesCategory = filters.category == null ||
                filters.category == "All" ||
                tourPackage.safeTitle.contains(filters.category!!, ignoreCase = true)
            
            val matchesMinPrice = filters.minPrice == null ||
                tourPackage.safePrice >= filters.minPrice
            
            val matchesMaxPrice = filters.maxPrice == null ||
                tourPackage.safePrice <= filters.maxPrice
            
            matchesCategory && matchesMinPrice && matchesMaxPrice
        }
        
        // Sort tour packages
        filteredTours = when (filters.sortBy) {
            SortOption.PRICE_LOW_HIGH -> filteredTours.sortedBy { it.safePrice }
            SortOption.PRICE_HIGH_LOW -> filteredTours.sortedByDescending { it.safePrice }
            SortOption.NEWEST -> filteredTours.sortedByDescending { it.id }
            SortOption.RATING -> filteredTours.sortedByDescending { it.safeRating }
        }
        
        // Apply pagination
        val paginatedDestinations = filtered.take(pageSize)
        
        _uiState.value = _uiState.value.copy(
            filteredDestinations = paginatedDestinations,
            filteredTourPackages = filteredTours,
            currentPage = 1,
            hasMorePages = filtered.size > pageSize
        )
    }
    
    // Toggle favorite for destination
    fun toggleDestinationFavorite(destinationId: Int) {
        val currentFavorites = _uiState.value.favoriteDestinationIds
        val newFavorites = if (currentFavorites.contains(destinationId)) {
            currentFavorites - destinationId
        } else {
            currentFavorites + destinationId
        }
        _uiState.value = _uiState.value.copy(favoriteDestinationIds = newFavorites)
        // TODO: Save to local database/API
    }
    
    // Toggle favorite for tour package
    fun toggleTourFavorite(tourId: Int) {
        val currentFavorites = _uiState.value.favoriteTourIds
        val newFavorites = if (currentFavorites.contains(tourId)) {
            currentFavorites - tourId
        } else {
            currentFavorites + tourId
        }
        _uiState.value = _uiState.value.copy(favoriteTourIds = newFavorites)
        // TODO: Save to local database/API
    }
    
    // Check if destination is favorite
    fun isDestinationFavorite(destinationId: Int): Boolean {
        return _uiState.value.favoriteDestinationIds.contains(destinationId)
    }
    
    // Check if tour is favorite
    fun isTourFavorite(tourId: Int): Boolean {
        return _uiState.value.favoriteTourIds.contains(tourId)
    }
    
    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}

