package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.annotation.StringRes
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.ETicket
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryJob
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryRules
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryStore
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.data.tickets.TicketUsageNoticeRules
import com.naylaaisyah.traveloapp.data.tickets.TicketUsageNoticeStore
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
    /**
     * Locally remember: bookings whose document upload did not finish. The
     * backend visitor list remains the authority; this list keeps them
     * discoverable even when the app starts offline.
     */
    val pendingRecovery: List<DocumentRecoveryJob> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    // Backend-issued e-ticket state. The QR is only shown after the API has
    // verified ownership and a confirmed payment.
    val ticketLoadingFor: Int? = null,
    val ticket: ETicket? = null,
    val ticketError: String? = null,
    @StringRes val ticketErrorRes: Int? = null,
    /**
     * A ticket that was active on the previous successful load and is now used.
     * Shown once after the backend confirms the gate scan.
     */
    val scannedTicketNotice: Booking? = null
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
    private val sessionManager: SessionManager,
    private val recoveryStore: DocumentRecoveryStore,
    private val usageNoticeStore: TicketUsageNoticeStore
) : ViewModel() {

    private val _uiState = MutableStateFlow(MyBookingsUiState())
    val uiState: StateFlow<MyBookingsUiState> = _uiState.asStateFlow()

    private var loadInFlight = false

    /**
     * @param silent background poll. It refreshes the list without showing the
     * full-screen/loading or pull-to-refresh indicator, so a gate scan can move
     * the ticket to history without disturbing an open screen.
     */
    fun loadBookings(silent: Boolean = false) {
        val token = sessionManager.authToken ?: return
        val userId = sessionManager.userId

        if (loadInFlight) {
            return
        }

        loadInFlight = true

        viewModelScope.launch {
            try {
                // Show the local recovery list immediately, even before (or
                // without) a successful network response.
                _uiState.value = _uiState.value.copy(
                    isLoading = if (silent) _uiState.value.isLoading else true,
                    error = if (silent) _uiState.value.error else null,
                    pendingRecovery = recoveryStore.pendingFor(userId)
                )

                when (val result = repository.getMyBookings(token)) {
                    is Resource.Success -> {
                        val bookings = result.data

                        // Reconcile local jobs with backend truth. A job is cleared
                        // once every visitor document is on file, or once the
                        // booking no longer belongs to this account/list.
                        val bookingById = bookings.associateBy { it.id }
                        recoveryStore.pendingFor(userId).forEach { job ->
                            val booking = bookingById[job.bookingId]
                            if (booking == null || !DocumentRecoveryRules.needsIdentityDocuments(booking)) {
                                recoveryStore.clear(job.bookingId, job.userId)
                            }
                        }

                        // Compare against the snapshot from the previous load.
                        // The backend is still the only source of `used`; this
                        // only decides whether to announce the transition.
                        val previousActiveIds = usageNoticeStore.previousActiveIds(userId)
                        val newlyUsed = TicketUsageNoticeRules.newlyUsed(bookings, previousActiveIds)
                        usageNoticeStore.saveActiveIds(
                            userId,
                            TicketUsageNoticeRules.activeIds(bookings)
                        )

                        _uiState.value = _uiState.value.copy(
                            bookings = bookings,
                            pendingRecovery = recoveryStore.pendingFor(userId),
                            isLoading = false,
                            error = null,
                            scannedTicketNotice = _uiState.value.scannedTicketNotice
                                ?: newlyUsed
                        )
                    }
                    is Resource.Error -> {
                        _uiState.value = _uiState.value.copy(
                            isLoading = if (silent) _uiState.value.isLoading else false,
                            pendingRecovery = recoveryStore.pendingFor(userId),
                            error = if (silent) _uiState.value.error else result.message
                        )
                    }
                    else -> {
                        if (!silent) {
                            _uiState.value = _uiState.value.copy(isLoading = false)
                        }
                    }
                }
            } finally {
                loadInFlight = false
            }
        }
    }

    fun dismissScannedTicketNotice() {
        _uiState.value = _uiState.value.copy(scannedTicketNotice = null)
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
