package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentWebViewActivity
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MyBookingsUiState(
    val bookings: List<Booking> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val paymentMessage: String? = null,
    val snapTokenToLaunch: String? = null,
    val showPaymentSuccessDialog: Boolean = false,
    val payingBookingId: Int? = null
)

@HiltViewModel
class MyBookingsViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(MyBookingsUiState())
    val uiState: StateFlow<MyBookingsUiState> = _uiState.asStateFlow()

    private var currentPayingBookingId: Int? = null

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

    fun startPendingPayment(booking: Booking) {
        val token = sessionManager.authToken ?: return
        viewModelScope.launch {
            currentPayingBookingId = booking.id
            _uiState.value = _uiState.value.copy(payingBookingId = booking.id)

            when (val result = repository.createSnapToken(token, booking.id)) {
                is Resource.Success -> {
                    val snapToken = result.data.snapToken
                    if (snapToken != null) {
                        _uiState.value = _uiState.value.copy(snapTokenToLaunch = snapToken)
                    } else {
                        currentPayingBookingId = null
                        _uiState.value = _uiState.value.copy(
                            payingBookingId = null,
                            paymentMessage = "Failed to get payment token"
                        )
                    }
                }
                is Resource.Error -> {
                    currentPayingBookingId = null
                    _uiState.value = _uiState.value.copy(
                        payingBookingId = null,
                        paymentMessage = result.message ?: "Payment failed"
                    )
                }
                else -> {}
            }
        }
    }

    fun onPaymentLaunchConsumed() {
        _uiState.value = _uiState.value.copy(snapTokenToLaunch = null)
    }

    fun onPaymentSuccess() {
        val bookingId = currentPayingBookingId
        android.util.Log.d("MyBookingsVM", "onPaymentSuccess bookingId=$bookingId")

        // FIX: do NOT show success dialog optimistically. The WebView success
        // signal only means the Snap page redirected; the backend may still say
        // pending (webhook delay / different order_id). Verify first.
        _uiState.value = _uiState.value.copy(
            showPaymentSuccessDialog = false,
            paymentMessage = "Payment received, verifying with server..."
        )

        if (bookingId != null) {
            verifyAndRefresh(bookingId)
        } else {
            loadBookings()
        }
    }

    fun onPaymentPending() {
        val bookingId = currentPayingBookingId
        android.util.Log.d("MyBookingsVM", "onPaymentPending bookingId=$bookingId")

        _uiState.value = _uiState.value.copy(
            paymentMessage = "Payment pending, verifying with server..."
        )

        if (bookingId != null) {
            verifyAndRefresh(bookingId)
        } else {
            loadBookings()
        }
    }

    fun onPaymentCancelled() {
        val bookingId = currentPayingBookingId
        android.util.Log.d("MyBookingsVM", "onPaymentCancelled - still verifying bookingId=$bookingId")

        _uiState.value = _uiState.value.copy(
            paymentMessage = "Checking payment status..."
        )

        if (bookingId != null) {
            verifyAndRefresh(bookingId)
        } else {
            currentPayingBookingId = null
            _uiState.value = _uiState.value.copy(payingBookingId = null)
            loadBookings()
        }
    }

    fun onPaymentResult() {
        val bookingId = currentPayingBookingId
        android.util.Log.d("MyBookingsVM", "onPaymentResult bookingId=$bookingId")

        if (bookingId != null) {
            verifyAndRefresh(bookingId)
        } else {
            currentPayingBookingId = null
            _uiState.value = _uiState.value.copy(payingBookingId = null)
            loadBookings()
        }
    }

    /**
     * Panggil backend untuk verifikasi status ke Midtrans,
     * lalu reload bookings. Retry max 3x kalau gagal.
     * FIX: only show the success dialog AFTER backend confirms paid/confirmed.
     * Previously the dialog was shown optimistically, then the list still said
     * pending -> user confusion.
     */
    private fun verifyAndRefresh(bookingId: Int) {
        val token = sessionManager.authToken ?: run {
            currentPayingBookingId = null
            _uiState.value = _uiState.value.copy(payingBookingId = null)
            loadBookings()
            return
        }

        _uiState.value = _uiState.value.copy(paymentMessage = "Verifying payment...")

        viewModelScope.launch {
            var confirmedPaid = false
            var terminalStatus: String? = null

            for (attempt in 1..3) {
                android.util.Log.d("MyBookingsVM", "Verify attempt $attempt/3 for booking $bookingId")
                delay(2000L * attempt)

                try {
                    repository.verifyAndSyncPayment(bookingId, token).collect { result ->
                        when (result) {
                            is Resource.Success -> {
                                val ps = result.data?.effectivePaymentStatus ?: result.data?.paymentStatus
                                val bs = result.data?.effectiveBookingStatus ?: result.data?.bookingStatus
                                android.util.Log.d("MyBookingsVM", "Attempt $attempt: paymentStatus=$ps bookingStatus=$bs")
                                if (ps == "paid" || bs == "confirmed") {
                                    confirmedPaid = true
                                    terminalStatus = "paid"
                                } else if (ps == "failed" || ps == "expired") {
                                    terminalStatus = ps
                                }
                            }
                            is Resource.Error -> {
                                android.util.Log.e("MyBookingsVM", "Attempt $attempt error: ${result.message}")
                            }
                            else -> {}
                        }
                    }
                } catch (e: Exception) {
                    android.util.Log.e("MyBookingsVM", "Attempt $attempt exception: ${e.message}")
                }

                if (confirmedPaid || terminalStatus == "failed" || terminalStatus == "expired") break
            }

            delay(500L)
            currentPayingBookingId = null
            if (confirmedPaid) {
                _uiState.value = _uiState.value.copy(
                    payingBookingId = null,
                    showPaymentSuccessDialog = true,
                    paymentMessage = "Payment successful!"
                )
            } else if (terminalStatus == "failed" || terminalStatus == "expired") {
                _uiState.value = _uiState.value.copy(
                    payingBookingId = null,
                    paymentMessage = "Payment $terminalStatus. Please try again."
                )
            } else {
                _uiState.value = _uiState.value.copy(
                    payingBookingId = null,
                    paymentMessage = "Payment is still pending. Please complete payment or check again shortly."
                )
            }
            loadBookings()
        }
    }

    fun clearMessage() {
        _uiState.value = _uiState.value.copy(paymentMessage = null)
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    fun dismissPaymentDialog() {
        currentPayingBookingId = null
        _uiState.value = _uiState.value.copy(
            showPaymentSuccessDialog = false,
            payingBookingId = null
        )
        loadBookings()
    }
}
