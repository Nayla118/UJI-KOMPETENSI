package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.annotation.StringRes
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Review
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.data.tickets.TicketHistoryRules
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class TicketHistoryUiState(
    val tickets: List<Booking> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    @StringRes val errorRes: Int? = null,
    val isSavingReview: Boolean = false,
    val reviewActionError: String? = null,
    @StringRes val reviewSuccessRes: Int? = null
)

/**
 * Ticket history is a read-only view over the same owner-scoped booking list.
 * Filtering uses the backend's `ticket_usage_status`; nothing is inferred from
 * a local timer or a locally generated token.
 */
@HiltViewModel
class TicketHistoryViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(TicketHistoryUiState())
    val uiState: StateFlow<TicketHistoryUiState> = _uiState.asStateFlow()

    fun loadHistory() {
        val token = sessionManager.authToken

        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = null,
                errorRes = R.string.session_expired
            )
            return
        }

        if (_uiState.value.isLoading) {
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isLoading = true,
                error = null,
                errorRes = null
            )

            when (val result = repository.getMyBookings(token)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        tickets = TicketHistoryRules.history(result.data),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    val message = result.message.takeIf { it.isNotBlank() }
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = message,
                        errorRes = if (message == null) {
                            R.string.ticket_history_load_failed
                        } else {
                            null
                        }
                    )
                }
                else -> Unit
            }
        }
    }

    /**
     * Submit the first review for a used ticket, or update the owner's
     * published review. Hidden reviews are deliberately not editable: the
     * server rejects that too, but the UI must not offer the action.
     */
    fun saveReview(booking: Booking, rating: Int, comment: String?) {
        val token = sessionManager.authToken

        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                reviewActionError = "Your session has expired. Please sign in again."
            )
            return
        }

        val existing = booking.review

        if (existing != null && !existing.isPublished) {
            _uiState.value = _uiState.value.copy(
                reviewActionError = "This review is under moderation and cannot be changed."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isSavingReview = true,
                reviewActionError = null,
                reviewSuccessRes = null
            )

            val result = if (existing != null) {
                repository.updateReview(token, existing.id, rating, comment)
            } else {
                repository.submitReview(token, booking.id, rating, comment)
            }

            when (result) {
                is Resource.Success -> {
                    // Reflect the saved review before the history refresh so
                    // the "rate" button disappears even if that request fails.
                    val saved = result.data
                    val updatedTickets = _uiState.value.tickets.map { ticket ->
                        if (ticket.id == booking.id) {
                            ticket.copy(
                                review = saved,
                                reviewStatus = "reviewed",
                                canReview = false
                            )
                        } else {
                            ticket
                        }
                    }

                    _uiState.value = _uiState.value.copy(
                        tickets = updatedTickets,
                        isSavingReview = false,
                        reviewActionError = null,
                        reviewSuccessRes = if (existing != null) {
                            R.string.review_updated
                        } else {
                            R.string.review_saved
                        }
                    )
                    loadHistory()
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isSavingReview = false,
                        reviewActionError = result.message
                    )
                }

                else -> Unit
            }
        }
    }

    fun deleteReview(review: Review) {
        val token = sessionManager.authToken

        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                reviewActionError = "Your session has expired. Please sign in again."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isSavingReview = true,
                reviewActionError = null,
                reviewSuccessRes = null
            )

            when (val result = repository.deleteReview(token, review.id)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        isSavingReview = false,
                        reviewActionError = null,
                        reviewSuccessRes = R.string.review_deleted
                    )
                    loadHistory()
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isSavingReview = false,
                        reviewActionError = result.message
                    )
                }

                else -> Unit
            }
        }
    }

    fun clearReviewFeedback() {
        _uiState.value = _uiState.value.copy(
            reviewActionError = null,
            reviewSuccessRes = null
        )
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(
            error = null,
            errorRes = null
        )
    }
}
