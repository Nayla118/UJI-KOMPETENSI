package com.naylaaisyah.traveloapp.ui.screens.payment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.paymentOutcome
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PaymentResultUiState(
    val isLoading: Boolean = true,
    val booking: Booking? = null,
    val outcome: PaymentOutcome = PaymentOutcome.WAITING,
    val error: String? = null
)

@HiltViewModel
class PaymentResultViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(PaymentResultUiState())
    val uiState: StateFlow<PaymentResultUiState> = _uiState.asStateFlow()

    /**
     * Loads backend truth for the booking. First asks the backend for one
     * best-effort Midtrans verification (covers the gap before the webhook
     * lands), then reads the booking. Single pass — no polling here; the
     * bounded reconciliation already happened on the payment screen.
     */
    fun loadResult(bookingId: Int) {
        val token = sessionManager.authToken ?: run {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "Please log in again to see your payment result."
            )
            return
        }
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            // Best-effort single verification; failures are fine — the
            // booking read below still reflects webhook-synced truth.
            try {
                repository.verifyAndSyncOnce(token, bookingId)
            } catch (_: Exception) {
            }
            when (val result = repository.getBookingOnce(token, bookingId)) {
                is Resource.Success -> {
                    val booking = result.data
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        booking = booking,
                        outcome = booking?.paymentOutcome() ?: PaymentOutcome.WAITING
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message ?: "Could not load payment result."
                    )
                }
                else -> {
                    _uiState.value = _uiState.value.copy(isLoading = false)
                }
            }
        }
    }
}
