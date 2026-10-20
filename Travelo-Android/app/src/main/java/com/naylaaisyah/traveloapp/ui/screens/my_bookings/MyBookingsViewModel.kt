package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.annotation.StringRes
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.ETicket
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MyBookingsUiState(
    val bookings: List<Booking> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    // Backend-issued e-ticket state. The QR is only shown after the API has
    // verified ownership and a confirmed payment.
    val ticketLoadingFor: Int? = null,
    val ticket: ETicket? = null,
    val ticketError: String? = null,
    @StringRes val ticketErrorRes: Int? = null
)

/**
 * Booking list. Payment status shown here always comes from the backend
 * (kept current by the Midtrans webhook + verification on the payment
 * screens). The list auto-refreshes whenever it is visible, so a booking
 * paid elsewhere turns Paid/Confirmed here with no manual step.
 *
 * Pay actions navigate to the dedicated Payment screen — there is no
 * "check status" button as a normal step.
 */
@HiltViewModel
class MyBookingsViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(MyBookingsUiState())
    val uiState: StateFlow<MyBookingsUiState> = _uiState.asStateFlow()

    fun loadBookings() {
        val token = sessionManager.authToken ?: return
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            when (val result = repository.getMyBookings(token)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        bookings = result.data,
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

    fun loadTicket(bookingId: Int) {
        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                ticketError = null,
                ticketErrorRes = R.string.session_expired
            )
            return
        }

        if (_uiState.value.ticketLoadingFor != null) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                ticketLoadingFor = bookingId,
                ticketError = null,
                ticketErrorRes = null,
                ticket = null
            )

            when (val result = repository.getETicket(token, bookingId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        ticketLoadingFor = null,
                        ticket = result.data
                    )
                }
                is Resource.Error -> {
                    val message = result.message.takeIf { it.isNotBlank() }
                    _uiState.value = _uiState.value.copy(
                        ticketLoadingFor = null,
                        ticketError = message,
                        ticketErrorRes = if (message == null) {
                            R.string.eticket_load_failed
                        } else {
                            null
                        }
                    )
                }
                else -> Unit
            }
        }
    }

    fun clearTicket() {
        _uiState.value = _uiState.value.copy(
            ticketLoadingFor = null,
            ticket = null,
            ticketError = null,
            ticketErrorRes = null
        )
    }

    fun clearTicketError() {
        _uiState.value = _uiState.value.copy(
            ticketError = null,
            ticketErrorRes = null
        )
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}
