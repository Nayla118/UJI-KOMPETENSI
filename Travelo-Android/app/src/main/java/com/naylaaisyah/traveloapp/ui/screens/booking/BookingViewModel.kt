package com.naylaaisyah.traveloapp.ui.screens.booking

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.BookingRequest
import com.naylaaisyah.traveloapp.data.models.ApiResponse
import com.naylaaisyah.traveloapp.data.models.CreateBookingResponseData
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

typealias CreateBookingResponse = ApiResponse<CreateBookingResponseData>

data class BookingUiState(
    val isLoading: Boolean = false,
    val createdBooking: Booking? = null,
    val snapToken: String? = null,
    val bookingSuccess: Boolean = false,
    val paymentSuccess: Boolean = false,
    val updatedBooking: Booking? = null,
    val error: String? = null
)

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
        totalPrice: Int
    ) {
        val userId = sessionManager.userId
        val token = sessionManager.authToken
        
        android.util.Log.d("BookingViewModel", "Creating booking...")
        android.util.Log.d("BookingViewModel", "userId: $userId")
        android.util.Log.d("BookingViewModel", "token: ${if (token != null) "present" else "null"}")
        
        if (userId == null) {
            android.util.Log.e("BookingViewModel", "User ID is null!")
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "User not logged in. Please login first."
            )
            return
        }
        
        if (token == null) {
            android.util.Log.e("BookingViewModel", "Auth token is null!")
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "Authentication error. Please login again."
            )
            return
        }

        val bookingRequest = BookingRequest(
            tourPackageId = tourPackageId,
            peopleCount = peopleCount,
            bookingDate = bookingDate
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
                        android.util.Log.e("BookingViewModel", "Resource error: ${result.message}")
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = result.message
                        )
                    }
                }
            }
        }
    }

    /**
     * Fetch updated booking after payment to get latest payment status
     */
    fun fetchUpdatedBooking(bookingId: Int) {
        val token = sessionManager.authToken ?: return

        viewModelScope.launch {
            android.util.Log.d("BookingViewModel", "Fetching booking $bookingId to check payment status...")
            
            repository.getBookingById(token, bookingId).collect { result ->
                when (result) {
                    is Resource.Success<*> -> {
                        val booking = result.data as? Booking
                        // Check both booking status and payment status
                        val bookingStatus = booking?.status ?: "pending"
                        val paymentStatus = booking?.payment?.status ?: "no_payment"
                        
                        android.util.Log.d("BookingViewModel", "=== BOOKING STATUS CHECK ===")
                        android.util.Log.d("BookingViewModel", "Booking ID: $bookingId")
                        android.util.Log.d("BookingViewModel", "Booking Status: $bookingStatus")
                        android.util.Log.d("BookingViewModel", "Payment Status: $paymentStatus")
                        android.util.Log.d("BookingViewModel", "Has Payment: ${booking?.payment != null}")
                        android.util.Log.d("BookingViewModel", "==========================")

                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            updatedBooking = booking,
                            paymentSuccess = bookingStatus == "confirmed" || paymentStatus == "paid"
                        )
                    }
                    is Resource.Error<*> -> {
                        android.util.Log.e("BookingViewModel", "Error fetching updated booking: ${result.message}")
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = result.message
                        )
                    }
                    else -> {
                        android.util.Log.d("BookingViewModel", "No result from repository")
                    }
                }
            }
        }
    }

    /**
     * Verify and sync payment with Midtrans, then fetch updated booking
     */
    fun verifyAndSyncPaymentStatus(bookingId: Int) {
        val token = sessionManager.authToken ?: return

        viewModelScope.launch {
            android.util.Log.d("BookingViewModel", "=== VERIFY AND SYNC PAYMENT ===")
            android.util.Log.d("BookingViewModel", "Booking ID: $bookingId")
            
            repository.verifyAndSyncPayment(bookingId, token).collect { result ->
                when (result) {
                    is Resource.Success<*> -> {
                        val paymentStatus = result.data as? com.naylaaisyah.traveloapp.data.models.PaymentSyncResponse
                        android.util.Log.d("BookingViewModel", "Payment status: ${paymentStatus?.paymentStatus}")
                        android.util.Log.d("BookingViewModel", "Booking status: ${paymentStatus?.bookingStatus}")
                        android.util.Log.d("BookingViewModel", "==============================")
                        
                        // Check if payment was successful
                        val isPaid = paymentStatus?.paymentStatus?.lowercase() == "paid" ||
                                    paymentStatus?.bookingStatus?.lowercase() == "confirmed"
                        
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            paymentSuccess = isPaid
                        )
                    }
                    is Resource.Error<*> -> {
                        android.util.Log.e("BookingViewModel", "Verify and sync failed: ${result.message}")
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = result.message
                        )
                    }
                    else -> {}
                }
            }
            
            // After sync completes, fetch updated booking
            android.util.Log.d("BookingViewModel", "Fetching updated booking after sync...")
            delay(1000)
            fetchUpdatedBooking(bookingId)
        }
    }

    /**
     * Retry payment sync if initial sync fails
     */
    fun retrySyncPaymentStatus(bookingId: Int) {
        val token = sessionManager.authToken ?: return

        viewModelScope.launch {
            android.util.Log.d("BookingViewModel", "=== RETRY SYNC PAYMENT ===")
            android.util.Log.d("BookingViewModel", "Booking ID: $bookingId")
            
            repository.retrySyncPayment(bookingId, token).collect { result ->
                when (result) {
                    is Resource.Success<*> -> {
                        val paymentStatus = result.data as? com.naylaaisyah.traveloapp.data.models.PaymentSyncResponse
                        android.util.Log.d("BookingViewModel", "Retry succeeded! Status: ${paymentStatus?.paymentStatus}")
                        
                        val isPaid = paymentStatus?.paymentStatus?.lowercase() == "paid" ||
                                    paymentStatus?.bookingStatus?.lowercase() == "confirmed"
                        
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            paymentSuccess = isPaid
                        )
                    }
                    is Resource.Error<*> -> {
                        android.util.Log.e("BookingViewModel", "Retry failed: ${result.message}")
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = result.message
                        )
                    }
                    else -> {}
                }
            }
            
            delay(1000)
            fetchUpdatedBooking(bookingId)
        }
    }

    fun resetState() {
        _uiState.value = BookingUiState()
    }
}

