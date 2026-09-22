package com.naylaaisyah.traveloapp.ui.screens.destination

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DestinationDetailUiState(
    val destination: Destination? = null,
    val tourPackages: List<TourPackage> = emptyList(),
    val isLoading: Boolean = true,
    val error: String? = null
)

@HiltViewModel
class DestinationDetailViewModel @Inject constructor(
    private val repository: TraveloRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(DestinationDetailUiState())
    val uiState: StateFlow<DestinationDetailUiState> = _uiState.asStateFlow()

    fun loadDestination(id: Int) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            when (val result = repository.getDestinationDetail(id)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        destination = result.data,
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                else -> {}
            }

            when (val result = repository.getTourPackagesByDestination(id)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(tourPackages = result.data)
                }
                else -> {}
            }
        }
    }
}