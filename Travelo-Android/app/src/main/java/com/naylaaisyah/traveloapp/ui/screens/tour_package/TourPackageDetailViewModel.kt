package com.naylaaisyah.traveloapp.ui.screens.tour_package

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.TourPackageDetail
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class TourPackageDetailUiState(
    val tourPackage: TourPackageDetail? = null,
    val isLoading: Boolean = true,
    val error: String? = null
)

@HiltViewModel
class TourPackageDetailViewModel @Inject constructor(
    private val repository: TraveloRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(TourPackageDetailUiState())
    val uiState: StateFlow<TourPackageDetailUiState> = _uiState.asStateFlow()

    fun loadTourPackage(id: Int) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            when (val result = repository.getTourPackageDetail(id)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        tourPackage = result.data,
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
        }
    }
}