package com.naylaaisyah.traveloapp.ui.screens.payment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.outcome
import com.naylaaisyah.traveloapp.data.models.paymentOutcome
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PaymentUiState(
    val isLoadingBooking: Boolean = true,
    val booking: Booking? = null,
    val isCreatingToken: Boolean = false,
    val snapTokenToLaunch: String? = null,
    val isReconciling: Boolean = false,
    val reconcileAttempt: Int = 0,
    val reconcileMax: Int = RECONCILE_MAX_POLLS + 1,
    val error: String? = null
)

/** Bounded reconciliation: 1 Midtrans verification + a few short status polls. Never infinite. */
const val RECONCILE_MAX_POLLS = 5
const val RECONCILE_POLL_DELAY_MS = 3000L

@HiltViewModel
class PaymentViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(PaymentUiState())
    val uiState: StateFlow<PaymentUiState> = _uiState.asStateFlow()

    private var reconcileJob: Job? = null

    fun loadBooking(bookingId: Int) {
        val token = sessionManager.authToken ?: run {
            _uiState.value = _uiState.value.copy(
                isLoadingBooking = false,
                error = "Please log in again to continue payment."
            )
            return
        }
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoadingBooking = true, error = null)
            when (val result = repository.getBookingOnce(token, bookingId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        isLoadingBooking = false,
                        booking = result.data,
                        error = null
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoadingBooking = false,
                        error = result.message ?: "Could not load booking."
                    )
                }
                else -> {
                    _uiState.value = _uiState.value.copy(isLoadingBooking = false)
                }
            }
        }
    }

    /**
     * Ask the backend for a fresh Snap token (fresh Midtrans order_id every
     * time — reusing an order_id is rejected by Midtrans) and emit it for
     * the payment page launcher.
     */
    fun startPayment() {
        val token = sessionManager.authToken ?: return
        val booking = _uiState.value.booking ?: return
        if (_uiState.value.isCreatingToken || _uiState.value.isReconciling) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isCreatingToken = true, error = null)
            when (val result = repository.createSnapToken(token, booking.id)) {
                is Resource.Success -> {
                    val snapToken = result.data.snapToken
                    if (snapToken != null) {
                        _uiState.value = _uiState.value.copy(
                            isCreatingToken = false,
                            snapTokenToLaunch = snapToken
                        )
                    } else {
                        _uiState.value = _uiState.value.copy(
                            isCreatingToken = false,
                            error = "Could not start payment. Please try again."
                        )
                    }
                }
                is Resource.Error -> {
                    val alreadyPaid = _uiState.value.booking?.paymentOutcome() == PaymentOutcome.SUCCESS
                    _uiState.value = _uiState.value.copy(
                        isCreatingToken = false,
                        error = if (alreadyPaid) {
                            "This booking is already paid."
                        } else {
                            result.message ?: "Could not start payment. Please try again."
                        }
                    )
                }
                else -> {
                    _uiState.value = _uiState.value.copy(isCreatingToken = false)
                }
            }
        }
    }

    fun onTokenConsumed() {
        _uiState.value = _uiState.value.copy(snapTokenToLaunch = null)
    }

    /**
     * Reconcile AFTER the user returns from the Midtrans payment page:
     *  1. one backend verification against Midtrans (webhook may be late),
     *  2. up to [RECONCILE_MAX_POLLS] short backend status polls.
     * Only backend truth decides the result. Bounded — always finishes.
     * Returns the final outcome so the caller can navigate to the result.
     */
    suspend fun reconcileAfterReturn(bookingId: Int): PaymentOutcome {
        val token = sessionManager.authToken ?: return PaymentOutcome.WAITING

        _uiState.value = _uiState.value.copy(isReconciling = true, reconcileAttempt = 0, error = null)

        // Step 1: single Midtrans verification via backend (best effort).
        when (repository.verifyAndSyncOnce(token, bookingId)) {
            is Resource.Success -> Unit
            is Resource.Error -> {
                // Network/Midtrans hiccup: fall through to status polls, which
                // still observe the webhook-updated DB state.
            }
            else -> Unit
        }
        _uiState.value = _uiState.value.copy(reconcileAttempt = 1)

        // Step 2: short bounded polls of backend truth (covers late webhook).
        repeat(RECONCILE_MAX_POLLS) { i ->
            val outcome = currentOutcome(token, bookingId)
            if (outcome != null && outcome != PaymentOutcome.WAITING) {
                _uiState.value = _uiState.value.copy(isReconciling = false)
                return outcome
            }
            _uiState.value = _uiState.value.copy(reconcileAttempt = i + 2)
            delay(RECONCILE_POLL_DELAY_MS)
        }

        val final = currentOutcome(token, bookingId) ?: PaymentOutcome.WAITING
        _uiState.value = _uiState.value.copy(isReconciling = false)
        return final
    }

    private suspend fun currentOutcome(token: String, bookingId: Int): PaymentOutcome? {
        return when (val result = repository.getPaymentStatus(token, bookingId)) {
            is Resource.Success -> result.data?.outcome()
            else -> null
        }
    }

    fun cancelReconcile() {
        reconcileJob?.cancel()
        reconcileJob = null
        _uiState.value = _uiState.value.copy(isReconciling = false)
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}
