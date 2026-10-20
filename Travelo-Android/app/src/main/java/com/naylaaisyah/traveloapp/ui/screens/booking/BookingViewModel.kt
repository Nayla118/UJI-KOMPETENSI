package com.naylaaisyah.traveloapp.ui.screens.booking

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.ApiResponse
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.BookingRequest
import com.naylaaisyah.traveloapp.data.models.CreateBookingResponseData
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

typealias CreateBookingResponse = ApiResponse<CreateBookingResponseData>

data class BookingUiState(
    val isLoading: Boolean = false,
    val createdBooking: Booking? = null,
    val snapToken: String? = null,
    val bookingSuccess: Boolean = false,
    val error: String? = null
)

/**
 * Step 1 of the flow: creates the booking (backend also issues the first
 * Snap token). Payment itself lives on the dedicated Payment screen, and
 * the result on the Payment Result screen — no payment logic here.
 */
@HiltViewModel
class BookingViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(BookingUiState())
    val uiState: StateFlow<BookingUiState> = _uiState.asStateFlow()

    fun createBooking(
        tourPackageId: Int,
        bookingDate: String,
        peopleCount: Int,
        specialRequests: String? = null
    ) {
        val userId = sessionManager.userId
        val token = sessionManager.authToken

        if (userId == null) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "User not logged in. Please login first."
            )
            return
        }

        if (token == null) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "Authentication error. Please login again."
            )
            return
        }

        val bookingRequest = BookingRequest(
            tourPackageId = tourPackageId,
            peopleCount = peopleCount,
            bookingDate = bookingDate,
            specialRequests = specialRequests?.trim()?.takeIf { it.isNotEmpty() }
        )

        viewModelScope.launch {
            repository.createBooking(token, bookingRequest).collect { result ->
                when (result) {
                    is Resource.Loading<*> -> {
                        _uiState.value = _uiState.value.copy(isLoading = true, error = null)
                    }
                    is Resource.Success<*> -> {
                        val response = result.data as? CreateBookingResponse ?: return@collect
                        if (response.success && response.data != null) {
                            _uiState.value = _uiState.value.copy(
                                isLoading = false,
                                createdBooking = response.data.booking,
                                snapToken = response.data.snapToken,
                                bookingSuccess = true,
                                error = null
                            )
                        } else {
                            _uiState.value = _uiState.value.copy(
                                isLoading = false,
                                error = response.message ?: "Failed to create booking"
                            )
                        }
                    }
                    is Resource.Error<*> -> {
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = result.message
                        )
                    }
                }
            }
        }
    }

    fun resetState() {
        _uiState.value = BookingUiState()
    }
}
