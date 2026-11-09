package com.naylaaisyah.traveloapp.data.repository

import android.util.Log
import com.naylaaisyah.traveloapp.data.api.ApiService
import com.naylaaisyah.traveloapp.data.models.*
import com.naylaaisyah.traveloapp.data.promo.PromoErrorMessages
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import com.google.gson.JsonSyntaxException
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.ResponseBody
import org.json.JSONObject
import retrofit2.HttpException
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

/**
 * A downloaded invoice document and the filename suggested by the server.
 * Kept in memory only until the UI writes it to app cache and opens/shares it.
 */
data class InvoiceFile(
    val bytes: ByteArray,
    val fileName: String
)

/**
 * Tag used for TraveloRepository diagnostics. Logs never contain tokens,
 * emails, or payment payloads.
 */
private const val TAG = "TraveloRepository"

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

    /**
     * Public catalog calls carry the Sanctum token when one exists so the
     * backend can optionally enrich them with `is_wishlisted`.
     */
    private fun optionalAuthHeader(): String? =
        sessionManager.authToken?.takeIf { it.isNotBlank() }?.let { "Bearer $it" }

    /** Reads the Laravel `{"message": "..."}` payload from a non-2xx response. */
    private fun serverMessage(e: HttpException): String? {        val body = try {
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

    // Destinations. Filters, sort and paging are resolved by the backend.
    suspend fun getDestinations(
        query: CatalogQuery = CatalogQuery()
    ): Resource<CatalogPage<Destination>> {
        return try {
            val response = apiService.getDestinations(
                query = query.toQueryMap(),
                token = optionalAuthHeader()
            )
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(
                    CatalogPage(
                        items = data,
                        meta = response.meta ?: CatalogMeta(
                            currentPage = query.page,
                            lastPage = 1,
                            perPage = query.perPage,
                            total = data.size
                        )
                    )
                )
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get destinations"))
        }
    }

    suspend fun getDestinationDetail(id: Int): Resource<Destination> {
        return try {
            Log.d(TAG, "GET destination detail id=$id")
            val response = apiService.getDestinationDetail(id, token = optionalAuthHeader())
            if (response.success && response.data != null) {
                Log.d(TAG, "destination detail id=$id loaded ok")
                Resource.Success(response.data)
            } else {
                Log.w(
                    TAG,
                    "destination detail id=$id failed: success=${response.success} message=${response.message ?: "-"}"
                )
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            val code = if (e is retrofit2.HttpException) "http-${e.code()}" else "transport"
            Log.e(TAG, "destination detail id=$id exception: $code", e)
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

    // Tour Packages. Filters, sort and paging are resolved by the backend.
    suspend fun getTourPackages(
        query: CatalogQuery = CatalogQuery()
    ): Resource<CatalogPage<TourPackage>> {
        return try {
            val response = apiService.getTourPackages(
                query = query.toQueryMap(),
                token = optionalAuthHeader()
            )
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(
                    CatalogPage(
                        items = data,
                        meta = response.meta ?: CatalogMeta(
                            currentPage = query.page,
                            lastPage = 1,
                            perPage = query.perPage,
                            total = data.size
                        )
                    )
                )
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get tour packages"))
        }
    }

    suspend fun getTourPackagesByDestination(destinationId: Int): Resource<List<TourPackage>> {
        return try {
            val response = apiService.getDestinationTourPackages(
                destinationId,
                token = optionalAuthHeader()
            )
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
            val response = apiService.getTourPackageDetail(id, token = optionalAuthHeader())
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Failed to get tour package detail"))
        }
    }

    /**
     * Per-date capacity for a tour package. Public, so a traveller can see
     * closed/full dates before submitting a booking. The server re-checks
     * capacity inside the booking transaction regardless of this response.
     */
    suspend fun getAvailability(
        tourPackageId: Int,
        month: String,
        people: Int = 1
    ): Resource<AvailabilityMonth> {
        return try {
            val response = apiService.getAvailability(
                id = tourPackageId,
                month = month,
                people = people.coerceAtLeast(1)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't load availability." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load availability."))
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

    /**
     * Preview a promo code. The server computes the price; the app only shows
     * the returned subtotal/discount/total and sends the code back on booking.
     */
    suspend fun validatePromo(
        token: String,
        request: PromoValidateRequest
    ): Resource<PromoPreview> {
        return try {
            val response = apiService.validatePromo("Bearer $token", request)
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { PromoErrorMessages.FALLBACK }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            if (e.code() == 401) {
                sessionManager.expireSession()
            }

            val body = try {
                e.response()?.errorBody()?.string()
            } catch (_: Exception) {
                null
            }

            val message = when (e.code()) {
                401 -> "Your session has expired. Please sign in again."
                else -> PromoErrorMessages.messageFromBody(
                    body,
                    "Couldn't apply this promo code."
                )
            }

            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't apply this promo code."))
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

    /**
     * Download the print-ready invoice for a paid booking. The backend returns
     * 409 until the payment is confirmed, so that message is surfaced as-is
     * instead of a generic failure.
     */
    suspend fun downloadInvoice(token: String, bookingId: Int): Resource<InvoiceFile> {
        return try {
            val response = apiService.downloadInvoice("Bearer $token", bookingId)

            if (!response.isSuccessful) {
                val message = errorBodyMessage(response)
                val friendly = when (response.code()) {
                    409 -> message
                        ?: "An invoice is available only after the payment is confirmed."
                    401, 403, 404 -> "This invoice isn't available on your account."
                    429 -> "Too many download attempts. Please wait a minute and try again."
                    else -> message ?: "Couldn't download your invoice."
                }
                Resource.Error(Exception(friendly), friendly)
            } else {
                val body = response.body()
                if (body == null) {
                    Resource.Error(
                        Exception("Invoice file was empty."),
                        "Couldn't download your invoice."
                    )
                } else {
                    val bytes = body.use { it.bytes() }
                    Resource.Success(
                        InvoiceFile(
                            bytes = bytes,
                            fileName = invoiceFileName(response, bookingId)
                        )
                    )
                }
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't download your invoice."))
        }
    }

    /** Reads Laravel's `{"message": "..."}` from a streamed error response. */
    private fun errorBodyMessage(response: Response<ResponseBody>): String? {
        val body = try {
            response.errorBody()?.string()
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
     * Extracts `filename="..."` from Content-Disposition. Only a safe basename
     * is kept so the value can never escape the app's invoice cache folder.
     */
    private fun invoiceFileName(response: Response<ResponseBody>, bookingId: Int): String {
        val header = response.headers()["Content-Disposition"].orEmpty()
        val raw = Regex("filename=\"?([^\";]+)\"?")
            .find(header)
            ?.groupValues
            ?.getOrNull(1)
            ?.trim()

        val safe = raw
            ?.substringAfterLast('/')
            ?.substringAfterLast('\\')
            ?.filter { it.isLetterOrDigit() || it == '.' || it == '-' || it == '_' }

        return safe?.takeIf { it.isNotBlank() && it.contains('.') }
            ?: "invoice-$bookingId.html"
    }

    /**
     * Cancellation requests for one of the caller's bookings. Requests are
     * advisory until an admin approves them.
     */
    suspend fun getChangeRequests(
        token: String,
        bookingId: Int
    ): Resource<List<BookingChangeRequest>> {
        return try {
            val response = apiService.getChangeRequests("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't load change requests." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load change requests."))
        }
    }

    suspend fun requestBookingCancellation(
        token: String,
        bookingId: Int,
        reason: String? = null
    ): Resource<BookingChangeRequest> {
        return try {
            val response = apiService.requestBookingChange(
                "Bearer $token",
                bookingId,
                BookingChangeRequestInput(
                    type = BookingChangeRequest.TYPE_CANCELLATION,
                    reason = reason?.trim()?.takeIf { it.isNotEmpty() }
                )
            )
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank {
                    "Couldn't submit your request. Please try again."
                }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't submit your request."))
        }
    }

    /** Estimated refund before submitting a cancellation request. */
    suspend fun getRefundPreview(token: String, bookingId: Int): Resource<RefundPreview> {
        return try {
            val response = apiService.getRefundPreview("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                val message = response.message.ifBlank { "Couldn't load refund estimate." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't load refund estimate."))
        }
    }

    /** Register/refresh this device's FCM token for the signed-in user. */
    suspend fun registerDeviceToken(token: String, fcmToken: String): Resource<Unit> {
        return try {
            val response = apiService.registerDeviceToken(
                "Bearer $token",
                DeviceTokenRequest(token = fcmToken)
            )
            if (response.success) {
                Resource.Success(Unit)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't enable notifications."))
        }
    }

    /** Remove this device's token so logout stops future push delivery. */
    suspend fun unregisterDeviceToken(token: String, fcmToken: String): Resource<Unit> {
        return try {
            val response = apiService.unregisterDeviceToken(
                "Bearer $token",
                DeviceTokenRequest(token = fcmToken)
            )
            if (response.success) {
                Resource.Success(Unit)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, readableError(e, "Couldn't disable notifications."))
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
