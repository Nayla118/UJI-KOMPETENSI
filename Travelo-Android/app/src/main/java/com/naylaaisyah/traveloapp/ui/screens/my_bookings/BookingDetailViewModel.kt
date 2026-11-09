package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.ETicket
import com.naylaaisyah.traveloapp.data.repository.InvoiceFile
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import retrofit2.HttpException
import javax.inject.Inject

data class BookingDetailUiState(
    val bookingId: Int = 0,
    val booking: Booking? = null,
    val isLoading: Boolean = true,
    val error: String? = null,
    /** Set for 403/404: show this on My Bookings after redirecting there. */
    val redirectNotice: String? = null,
    val ticketLoadingFor: Int? = null,
    val ticket: ETicket? = null,
    val ticketError: String? = null,
    val invoiceDownloading: Boolean = false,
    val invoiceFile: InvoiceFile? = null,
    val invoiceError: String? = null
)

/**
 * Detail view used by notification deep links. Ownership is checked by the
 * backend; 403/404 is surfaced as a friendly message and the caller redirects
 * to My Bookings instead of leaving the user on a dead screen.
 */
@HiltViewModel
class BookingDetailViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(BookingDetailUiState())
    val uiState: StateFlow<BookingDetailUiState> = _uiState.asStateFlow()

    private var loadedBookingId: Int? = null

    fun load(bookingId: Int, openTicket: Boolean = false, force: Boolean = false) {
        if (!force && loadedBookingId == bookingId && _uiState.value.booking != null) {
            if (openTicket) loadTicket(bookingId)
            return
        }

        loadedBookingId = bookingId

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.value = BookingDetailUiState(
                bookingId = bookingId,
                isLoading = false,
                error = "Your session has expired. Please sign in again."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = BookingDetailUiState(bookingId = bookingId)

            when (val result = repository.getBookingOnce(token, bookingId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        booking = result.data,
                        isLoading = false,
                        error = null
                    )
                    if (openTicket) {
                        loadTicket(bookingId)
                    }
                }

                is Resource.Error -> {
                    val code = (result.exception as? HttpException)?.code()
                    val notice = when (code) {
                        403 -> "This booking isn't available on your account. " +
                            "We've opened My Bookings so you can check your trips."
                        404 -> "We couldn't find that booking. " +
                            "We've opened My Bookings so you can check your trips."
                        else -> null
                    }

                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = notice ?: result.message,
                        redirectNotice = notice
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun loadTicket(bookingId: Int = _uiState.value.bookingId) {
        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                ticketError = "Your session has expired. Please sign in again."
            )
            return
        }

        if (_uiState.value.ticketLoadingFor != null) return
        if (_uiState.value.ticket?.bookingId == bookingId) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                ticketLoadingFor = bookingId,
                ticket = null,
                ticketError = null
            )

            when (val result = repository.getETicket(token, bookingId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        ticketLoadingFor = null,
                        ticket = result.data
                    )
                }

                is Resource.Error -> {
                    val code = (result.exception as? HttpException)?.code()
                    val notice = when (code) {
                        403 -> "This e-ticket isn't available on your account."
                        404 -> "We couldn't find that booking's e-ticket."
                        else -> null
                    }

                    _uiState.value = _uiState.value.copy(
                        ticketLoadingFor = null,
                        ticketError = if (notice == null) result.message else null,
                        redirectNotice = notice
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun clearTicket() {
        _uiState.value = _uiState.value.copy(ticket = null, ticketError = null)
    }

    /**
     * Fetch the invoice document for a paid booking. The screen writes the
     * bytes to cache and opens/shares the file, then clears [invoiceFile].
     */
    fun downloadInvoice(bookingId: Int = _uiState.value.bookingId) {
        if (_uiState.value.invoiceDownloading) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                invoiceError = "Your session has expired. Please sign in again."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                invoiceDownloading = true,
                invoiceFile = null,
                invoiceError = null
            )

            when (val result = repository.downloadInvoice(token, bookingId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        invoiceDownloading = false,
                        invoiceFile = result.data
                    )
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        invoiceDownloading = false,
                        invoiceError = result.message
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun consumeInvoiceFile() {
        _uiState.value = _uiState.value.copy(invoiceFile = null)
    }

    fun consumeInvoiceError() {
        _uiState.value = _uiState.value.copy(invoiceError = null)
    }

    fun consumeTicketError() {
        _uiState.value = _uiState.value.copy(ticketError = null)
    }

    fun consumeRedirectNotice() {
        _uiState.value = _uiState.value.copy(redirectNotice = null)
    }

    fun retry() {
        val bookingId = _uiState.value.bookingId
        if (bookingId > 0) {
            load(bookingId, force = true)
        }
    }
}
