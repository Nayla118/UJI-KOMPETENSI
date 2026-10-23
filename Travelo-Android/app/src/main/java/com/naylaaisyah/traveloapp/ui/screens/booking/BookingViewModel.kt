package com.naylaaisyah.traveloapp.ui.screens.booking

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.ApiResponse
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.BookingRequest
import com.naylaaisyah.traveloapp.data.models.CreateBookingResponseData
import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import com.naylaaisyah.traveloapp.data.models.Visitor
import com.naylaaisyah.traveloapp.data.models.VisitorDocumentInput
import com.naylaaisyah.traveloapp.data.models.VisitorInput
import com.naylaaisyah.traveloapp.data.models.VisitorRequest
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryJob
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryStore
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
    val awaitingDocumentUploads: Boolean = false,
    val bookerName: String = "",
    val savedIdentity: SavedIdentity? = null,
    val savedIdentityLoaded: Boolean = false,
    val error: String? = null
)

/**
 * Step 1 of the flow: creates the booking (backend also issues the first
 * Snap token) and uploads each visitor's identity document.
 *
 * The booking is created first because a visitor needs an id before a
 * document can be attached. If an upload fails, the created booking is kept
 * and the screen offers a retry: the backend refuses to issue an e-ticket
 * until every visitor document is on file, so silently continuing to payment
 * would strand the traveller.
 */
@HiltViewModel
class BookingViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager,
    private val recoveryStore: DocumentRecoveryStore
) : ViewModel() {

    private val _uiState = MutableStateFlow(BookingUiState())
    val uiState: StateFlow<BookingUiState> = _uiState.asStateFlow()

    init {
        _uiState.value = BookingUiState(bookerName = sessionManager.userName.orEmpty())
        loadSavedIdentity()
    }

    /**
     * Best-effort prefill. If the call fails the screen simply falls back to
     * manual entry; this must never block a booking.
     */
    private fun loadSavedIdentity() {
        val token = sessionManager.authToken ?: run {
            _uiState.value = _uiState.value.copy(savedIdentityLoaded = true)
            return
        }

        viewModelScope.launch {
            when (val result = repository.getSavedIdentity(token)) {
                is Resource.Success<*> -> {
                    _uiState.value = _uiState.value.copy(
                        savedIdentity = result.data as? SavedIdentity,
                        savedIdentityLoaded = true
                    )
                }
                is Resource.Error<*> -> {
                    _uiState.value = _uiState.value.copy(savedIdentityLoaded = true)
                }
                is Resource.Loading<*> -> Unit
            }
        }
    }

    fun createBooking(
        tourPackageId: Int,
        bookingDate: String,
        peopleCount: Int,
        specialRequests: String? = null,
        visitors: List<VisitorInput> = emptyList(),
        documents: List<VisitorDocumentInput?> = emptyList(),
        reuseSavedIdentityDocument: Boolean = false
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

        // Retry path: the booking exists, only the documents failed.
        val existing = _uiState.value.createdBooking
        if (existing != null && _uiState.value.awaitingDocumentUploads) {
            viewModelScope.launch {
                _uiState.value = _uiState.value.copy(isLoading = true, error = null)
                uploadDocuments(token, userId, existing, documents)
            }
            return
        }

        val visitorRequests = visitors.mapIndexed { index, visitor ->
            VisitorRequest(
                name = visitor.name.trim(),
                identityType = visitor.identityType,
                identityTypeOther = visitor.identityTypeOther?.trim()?.takeIf { it.isNotEmpty() },
                identityNumber = visitor.identityNumber.trim(),
                dateOfBirth = visitor.dateOfBirth,
                phone = visitor.phone?.trim()?.takeIf { it.isNotEmpty() },
                isBooker = index == 0
            )
        }.takeIf { it.isNotEmpty() }

        val bookingRequest = BookingRequest(
            tourPackageId = tourPackageId,
            peopleCount = peopleCount,
            bookingDate = bookingDate,
            specialRequests = specialRequests?.trim()?.takeIf { it.isNotEmpty() },
            visitors = visitorRequests,
            privacyConsent = if (visitorRequests != null) true else null,
            reuseSavedIdentityDocument = if (visitorRequests != null && reuseSavedIdentityDocument) {
                true
            } else {
                null
            }
        )

        viewModelScope.launch {
            repository.createBooking(token, bookingRequest).collect { result ->
                when (result) {
                    is Resource.Loading<*> -> {
                        _uiState.value = _uiState.value.copy(isLoading = true, error = null)
                    }
                    is Resource.Success<*> -> {
                        val response = result.data as? CreateBookingResponse ?: return@collect
                        val data = response.data
                        if (response.success && data != null) {
                            // Persist the recovery reference before the first
                            // upload attempt. If the process dies here, My
                            // Bookings can still point the user back to this
                            // booking without creating a second one.
                            recoveryStore.markPending(
                                DocumentRecoveryJob(
                                    bookingId = data.booking.id,
                                    userId = userId,
                                    tourTitle = data.booking.tourPackage?.title
                                        ?: "Booking #${data.booking.id}",
                                    peopleCount = data.booking.peopleCount,
                                    createdAt = System.currentTimeMillis()
                                )
                            )

                            _uiState.value = _uiState.value.copy(
                                isLoading = true,
                                createdBooking = data.booking,
                                snapToken = data.snapToken,
                                bookingSuccess = false,
                                awaitingDocumentUploads = true,
                                error = null
                            )

                            uploadDocuments(token, userId, data.booking, documents)
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

    private suspend fun uploadDocuments(
        token: String,
        userId: String,
        booking: Booking,
        documents: List<VisitorDocumentInput?>
    ) {
        if (booking.visitors.isEmpty()) {
            // Legacy/no-visitor booking: nothing to upload.
            recoveryStore.clear(booking.id, userId)
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                bookingSuccess = true,
                awaitingDocumentUploads = false,
                error = null
            )
            return
        }

        for ((index, visitor) in booking.visitors.withIndex()) {
            // The backend may already have some files from an earlier attempt.
            // Never upload those again; only fill the remaining gaps.
            if (visitor.hasDocument) continue

            val document = documents.getOrNull(index)

            if (document == null) {
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    awaitingDocumentUploads = true,
                    error = "Identity document for traveller ${index + 1} is missing. Select it again and retry."
                )
                return
            }

            when (val result = repository.uploadVisitorDocument(
                token = token,
                bookingId = booking.id,
                visitorId = visitor.id,
                fileName = document.fileName,
                mimeType = document.mimeType,
                bytes = document.bytes
            )) {
                is Resource.Success<*> -> {
                    // Keep the in-memory booking in sync so a retry in this
                    // screen skips visitors that just succeeded.
                    val uploaded = result.data as? Visitor
                    if (uploaded != null) {
                        val updatedVisitors = _uiState.value.createdBooking
                            ?.visitors
                            .orEmpty()
                            .map { if (it.id == uploaded.id) uploaded else it }
                        _uiState.value.createdBooking?.let { current ->
                            _uiState.value = _uiState.value.copy(
                                createdBooking = current.copy(visitors = updatedVisitors)
                            )
                        }
                    }
                }
                is Resource.Error<*> -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        awaitingDocumentUploads = true,
                        error = "Booking saved, but the identity upload failed: ${result.message}. Tap retry."
                    )
                    return
                }
                is Resource.Loading<*> -> Unit
            }
        }

        recoveryStore.clear(booking.id, userId)
        _uiState.value = _uiState.value.copy(
            isLoading = false,
            bookingSuccess = true,
            awaitingDocumentUploads = false,
            error = null
        )
    }

    fun resetState() {
        val bookerName = _uiState.value.bookerName
        _uiState.value = BookingUiState(bookerName = bookerName)
        // The first booking is what creates the saved identity. Reload it so
        // returning to this screen (or starting the next booking) can prefill
        // immediately instead of waiting for a fresh ViewModel.
        loadSavedIdentity()
    }
}
