package com.naylaaisyah.traveloapp.data.repository

import com.naylaaisyah.traveloapp.data.api.ApiService
import com.naylaaisyah.traveloapp.data.models.*
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import com.google.gson.JsonSyntaxException
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import retrofit2.HttpException
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class TraveloRepository @Inject constructor(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) {
    // Authentication
    suspend fun login(idToken: String): Resource<AuthResponse> {
        return try {
            val response = apiService.login(FirebaseLoginRequest(idToken))
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { "Login failed" }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Sign-in failed (${e.code()})."
            Resource.Error(e, message)
        } catch (e: Exception) {
            val message = readableError(e, "Login failed")
            Resource.Error(e, message)
        }
    }

    fun firebaseLogin(idToken: String): Flow<Resource<Pair<User, String>>> = flow {
        emit(Resource.Loading<Pair<User, String>>())
        try {
            val response = apiService.login(FirebaseLoginRequest(idToken))
            if (response.success && response.data?.token != null && response.data?.user != null) {
                emit(Resource.Success(Pair(response.data.user, response.data.token)))
            } else {
                emit(Resource.Error(Exception(response.data?.message ?: "Login failed")))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, readableError(e, "Firebase login failed")))
        }
    }

    suspend fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String
    ): Resource<AuthResponse> {
        return try {
            val response = apiService.register(
                RegisterRequest(name, email, password, passwordConfirmation)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Registration failed"))
        }
    }

    // Email OTP verification (login step 2)
    suspend fun sendOtp(idToken: String): Resource<OtpSendResponse> =
        otpCall(fallback = "Couldn't send the verification code. Please try again.") {
            apiService.sendOtp(FirebaseLoginRequest(idToken))
        }

    suspend fun resendOtp(idToken: String): Resource<OtpSendResponse> =
        otpCall(fallback = "Couldn't resend the code. Please try again.") {
            apiService.resendOtp(FirebaseLoginRequest(idToken))
        }

    /**
     * Verifies the 6-digit code. Only a correct, unexpired, single-use code
     * makes the backend return the session token.
     */
    suspend fun verifyOtp(idToken: String, code: String): Resource<Pair<User, String>> {
        return try {
            val response = apiService.verifyOtp(OtpVerifyRequest(idToken, code))
            val data = response.data
            val user = data?.user
            val token = data?.token
            if (response.success && user != null && token != null) {
                Resource.Success(user to token)
            } else {
                val message = response.message.ifBlank { "That code is incorrect or has expired." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "That code is incorrect or has expired."
            Resource.Error(e, message)
        } catch (e: Exception) {
            val message = readableError(e, "Couldn't reach the server.")
            Resource.Error(e, message)
        }
    }

    private suspend fun <T> otpCall(
        fallback: String,
        call: suspend () -> ApiResponse<T>
    ): Resource<T> {
        return try {
            val response = call()
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { fallback }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: fallback
            Resource.Error(e, message)
        } catch (e: Exception) {
            val message = readableError(e, fallback)
            Resource.Error(e, message)
        }
    }

    /** Reads the Laravel `{"message": "..."}` payload from a non-2xx response. */
    private fun serverMessage(e: HttpException): String? {
        val body = try {
            e.response()?.errorBody()?.string()
        } catch (_: Exception) {
            null
        }

        if (body.isNullOrBlank()) return null

        return try {
            JSONObject(body).optString("message").takeIf { it.isNotBlank() }
        } catch (_: Exception) {
            null
        }
    }

    /**
     * Turns transport exceptions into copy a person can act on. Raw OkHttp
     * text ("failed to connect to /10.0.2.2 (port 8000) ...") must never reach
     * the UI; the original exception is still logged by the caller's stack.
     */
    private fun readableError(e: Throwable, fallback: String): String {
        if (e is HttpException && e.code() == 401) {
            // Clear the stale Sanctum token and let MainActivity return to
            // LoginActivity. The message is still returned for the local UI.
            sessionManager.expireSession()
        }

        return when (e) {
            is HttpException -> when (e.code()) {
                401 -> "Your session has expired. Please sign in again."
                403 -> "You don't have access to this booking."
                else -> serverMessage(e) ?: fallback
            }
            is JsonSyntaxException -> "We couldn't read the server response. Please try again."
            is java.net.SocketTimeoutException -> "The connection timed out. Please try again."
            is java.net.UnknownHostException -> "Can't reach Travelo. Check your internet connection."
            is java.net.ConnectException -> "Can't reach Travelo. Check your internet connection."
            is java.io.IOException -> "Network problem. Check your connection and try again."
            else -> e.message?.takeIf { it.isNotBlank() } ?: fallback
        }
    }

    // Destinations
    suspend fun getDestinations(page: Int = 1): Resource<List<Destination>> {
        return try {
            val response = apiService.getDestinations(page)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get destinations"))
        }
    }

    suspend fun getDestinationDetail(id: Int): Resource<Destination> {
        return try {
            val response = apiService.getDestinationDetail(id)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get destination detail"))
        }
    }

    // Verified-visit reviews
    suspend fun getDestinationReviews(
        destinationId: Int,
        page: Int = 1,
        perPage: Int = 5
    ): Resource<ReviewListData> {
        return try {
            val response = apiService.getDestinationReviews(destinationId, page, perPage)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load reviews."))
        }
    }

    suspend fun getReviewEligibility(
        token: String,
        destinationId: Int
    ): Resource<ReviewEligibility> {
        return try {
            val response = apiService.getReviewEligibility("Bearer $token", destinationId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't check your review eligibility."))
        }
    }

    suspend fun submitReview(
        token: String,
        bookingId: Int,
        rating: Int,
        comment: String?
    ): Resource<Review> {
        return try {
            val response = apiService.submitReview(
                "Bearer $token",
                bookingId,
                ReviewRequest(rating = rating, comment = comment)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't save your review." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't save your review."))
        }
    }

    suspend fun updateReview(
        token: String,
        reviewId: Int,
        rating: Int,
        comment: String?
    ): Resource<Review> {
        return try {
            val response = apiService.updateReview(
                "Bearer $token",
                reviewId,
                ReviewRequest(rating = rating, comment = comment)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't update your review." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't update your review."))
        }
    }

    suspend fun deleteReview(token: String, reviewId: Int): Resource<Unit> {
        return try {
            val response = apiService.deleteReview("Bearer $token", reviewId)
            if (response.success) {
                Resource.Success(Unit)
            } else {
                val message = response.message.ifBlank { "Couldn't delete your review." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't delete your review."))
        }
    }

    // Tour Packages
    suspend fun getTourPackages(page: Int = 1): Resource<List<TourPackage>> {
        return try {
            val response = apiService.getTourPackages(page)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get tour packages"))
        }
    }

    suspend fun getTourPackagesByDestination(destinationId: Int): Resource<List<TourPackage>> {
        return try {
            val response = apiService.getDestinationTourPackages(destinationId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get tour packages"))
        }
    }

    suspend fun getTourPackageDetail(id: Int): Resource<TourPackageDetail> {
        return try {
            val response = apiService.getTourPackageDetail(id)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get tour package detail"))
        }
    }

    // Bookings
    suspend fun getMyBookings(token: String): Resource<List<Booking>> {
        return try {
            val response = apiService.getMyBookings("Bearer $token")
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get bookings"))
        }
    }

    fun createBooking(
        token: String,
        request: BookingRequest
    ): Flow<Resource<CreateBookingResponse>> = flow {
        emit(Resource.Loading<CreateBookingResponse>())
        try {
            val response = apiService.createBooking("Bearer $token", request)
            val data = response.data
            if (response.success && data?.booking != null) {
                emit(
                    Resource.Success(
                        ApiResponse(
                            success = true,
                            message = response.message.ifBlank { "Booking created successfully" },
                            data = data
                        )
                    )
                )
            } else {
                val message = response.message.ifBlank { "Failed to create booking" }
                emit(Resource.Error(Exception(message), message))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, readableError(e, "Failed to create booking")))
        }
    }

    /**
     * Upload one identity document to private server storage. The backend
     * validates content type/size itself; the client-side check only exists
     * for faster feedback.
     */
    suspend fun uploadVisitorDocument(
        token: String,
        bookingId: Int,
        visitorId: Int,
        fileName: String,
        mimeType: String,
        bytes: ByteArray
    ): Resource<Visitor> {
        return try {
            val body = bytes.toRequestBody(mimeType.toMediaTypeOrNull())
            val part = MultipartBody.Part.createFormData("document", fileName, body)
            val response = apiService.uploadVisitorDocument(
                "Bearer $token",
                bookingId,
                visitorId,
                part
            )
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { "Couldn't upload the document." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Couldn't upload the document."
            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't upload the document."))
        }
    }

    suspend fun getVisitors(token: String, bookingId: Int): Resource<List<Visitor>> {
        return try {
            val response = apiService.getVisitors("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load visitor data."))
        }
    }

    fun getBookingById(token: String, bookingId: Int): Flow<Resource<Booking>> = flow {
        emit(Resource.Loading<Booking>())
        try {
            val response = apiService.getBookingDetail("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                emit(Resource.Success(response.data))
            } else {
                emit(Resource.Error(Exception(response.message), response.message))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, readableError(e, "Failed to get booking")))
        }
    }

    suspend fun createSnapToken(token: String, bookingId: Int): Resource<CreateBookingResponseData> {
        return try {
            val response = apiService.createSnapToken("Bearer $token", SnapTokenRequest(bookingId))
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to create snap token"))
        }
    }

    suspend fun getPaymentStatus(token: String, bookingId: Int): Resource<PaymentStatusData> {
        return try {
            val response = apiService.getPaymentStatus("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get payment status"))
        }
    }

    /**
     * Single-shot Midtrans reconciliation: asks the backend to verify this
     * booking against Midtrans (Status API across all order_ids) and sync
     * the local DB. Returns the backend-confirmed statuses, or an error.
     * Callers must bound retries themselves — never loop forever.
     */
    suspend fun verifyAndSyncOnce(token: String, bookingId: Int): Resource<PaymentSyncResponse> {
        return try {
            val response = apiService.verifyAndSyncPayment("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Payment verification failed"))
        }
    }

    suspend fun getBookingOnce(token: String, bookingId: Int): Resource<Booking> {
        return try {
            val response = apiService.getBookingDetail("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get booking"))
        }
    }

    /**
     * Fetch the backend-verified e-ticket. A 409 means the booking is not paid
     * and confirmed yet, so the UI must show that message instead of a QR.
     */
    suspend fun getETicket(token: String, bookingId: Int): Resource<ETicket> {
        return try {
            val response = apiService.getETicket("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message.ifBlank {
                    "E-ticket is not available for this booking."
                })
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load your e-ticket."))
        }
    }

    // User
    suspend fun getUserProfile(token: String): Resource<User> {
        return try {
            val response = apiService.getUserProfile("Bearer $token")
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load your profile."))
        }
    }

    /**
     * The account's saved booker identity. A successful response with no
     * data means this account has not supplied identity data yet, which is
     * not an error.
     */
    suspend fun getSavedIdentity(token: String): Resource<SavedIdentity?> {
        return try {
            val response = apiService.getSavedIdentity("Bearer $token")
            if (response.success) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message), response.message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load your saved identity."))
        }
    }

    suspend fun deleteSavedIdentity(token: String): Resource<Unit> {
        return try {
            val response = apiService.deleteSavedIdentity("Bearer $token")
            if (response.success) {
                Resource.Success(Unit)
            } else {
                val message = response.message.ifBlank {
                    "Couldn't delete your saved identity."
                }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't delete your saved identity."))
        }
    }

    suspend fun uploadProfilePhoto(
        token: String,
        fileName: String,
        mimeType: String,
        bytes: ByteArray
    ): Resource<User> {
        return try {
            val body = bytes.toRequestBody(mimeType.toMediaTypeOrNull())
            val part = MultipartBody.Part.createFormData("photo", fileName, body)
            val response = apiService.uploadProfilePhoto("Bearer $token", part)
            val data = response.data

            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { "Couldn't update your profile photo." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Couldn't update your profile photo."
            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't update your profile photo."))
        }
    }

    suspend fun updateProfile(
        token: String,
        name: String,
        phone: String?,
        dateOfBirth: String?,
        city: String?
    ): Resource<User> {
        return try {
            val response = apiService.updateProfile(
                "Bearer $token",
                UpdateProfileRequest(
                    name = name,
                    phone = phone,
                    dateOfBirth = dateOfBirth,
                    city = city
                )
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't save your profile." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't save your profile."))
        }
    }
}
