package com.naylaaisyah.traveloapp.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject

data class HomeUiState(
    val isLoading: Boolean = false,
    val searchQuery: String = "",
    val popularDestinations: List<Destination> = emptyList(),
    val featuredTours: List<TourPackage> = emptyList(),
    val userName: String = "",
    val userPhoto: String? = null,
    val error: String? = null
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()

    init {
        loadUserInfo()
        refresh()
    }

    private fun loadUserInfo() {
        _uiState.value = _uiState.value.copy(
            userName = sessionManager.userName.orEmpty(),
            userPhoto = sessionManager.userPhoto
        )
    }

    fun refresh() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            when (val destinationResult = repository.getDestinations()) {
                is Resource.Success -> {
                    val destinationList = destinationResult.data.orEmpty()
                    _uiState.value = _uiState.value.copy(popularDestinations = destinationList)
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(error = destinationResult.message)
                }
                is Resource.Loading<*> -> Unit
            }

            when (val tourResult = repository.getTourPackages()) {
                is Resource.Success -> {
                    val tourList = tourResult.data.orEmpty()
                    _uiState.value = _uiState.value.copy(featuredTours = tourList)
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(error = tourResult.message)
                }
                is Resource.Loading<*> -> Unit
            }

            _uiState.value = _uiState.value.copy(isLoading = false)
        }
    }

    fun updateSearchQuery(query: String) {
        _uiState.value = _uiState.value.copy(searchQuery = query)
    }
}