package com.naylaaisyah.traveloapp.data.api

import com.naylaaisyah.traveloapp.data.models.*
import okhttp3.MultipartBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    // Authentication Endpoints
    @POST("api/auth/login")
    suspend fun login(@Body request: FirebaseLoginRequest): ApiResponse<AuthResponse>

    @POST("api/auth/register")
    suspend fun register(@Body request: RegisterRequest): ApiResponse<AuthResponse>

    // Email OTP verification — issued only after a correct, unexpired, single-use code
    @POST("api/auth/otp/send")
    suspend fun sendOtp(@Body request: FirebaseLoginRequest): ApiResponse<OtpSendResponse>

    @POST("api/auth/otp/resend")
    suspend fun resendOtp(@Body request: FirebaseLoginRequest): ApiResponse<OtpSendResponse>

    @POST("api/auth/otp/verify")
    suspend fun verifyOtp(@Body request: OtpVerifyRequest): ApiResponse<AuthResponse>

    @POST("api/auth/logout")
    suspend fun logout(@Header("Authorization") token: String): ApiResponse<Any>

    @GET("api/auth/me")
    suspend fun getUserProfile(
        @Header("Authorization") token: String
    ): ApiResponse<User>

    @PUT("api/auth/profile")
    suspend fun updateProfile(
        @Header("Authorization") token: String,
        @Body request: UpdateProfileRequest
    ): ApiResponse<User>

    @Multipart
    @POST("api/auth/profile/photo")
    suspend fun uploadProfilePhoto(
        @Header("Authorization") token: String,
        @Part photo: MultipartBody.Part
    ): ApiResponse<User>

    // The booker's saved identity for prefilling later bookings. This is
    // owner-only and the response includes the raw number for the form.
    @GET("api/auth/identity")
    suspend fun getSavedIdentity(
        @Header("Authorization") token: String
    ): ApiResponse<SavedIdentity>

    // Removes the account-level identity and its private document copy. Past
    // bookings and their own document copies stay untouched.
    @DELETE("api/auth/identity")
    suspend fun deleteSavedIdentity(
        @Header("Authorization") token: String
    ): ApiResponse<Any>

    // Destination Endpoints. The Authorization header is optional: the
    // backend returns `is_wishlisted` only when a valid Sanctum token is sent.
    @GET("api/destinations")
    suspend fun getDestinations(
        @QueryMap query: Map<String, String>,
        @Header("Authorization") token: String? = null
    ): ApiResponse<List<Destination>>

    @GET("api/destinations/popular")
    suspend fun getPopularDestinations(
        @Query("limit") limit: Int = 6,
        @Header("Authorization") token: String? = null
    ): ApiResponse<List<Destination>>

    @GET("api/destinations/{id}")
    suspend fun getDestinationDetail(
        @Path("id") id: Int,
        @Header("Authorization") token: String? = null
    ): ApiResponse<Destination>

    // Verified-visit reviews. Public lists contain only published reviews;
    // eligibility and submissions are authenticated and booking-scoped.
    @GET("api/destinations/{id}/reviews")
    suspend fun getDestinationReviews(
        @Path("id") destinationId: Int,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 5
    ): ApiResponse<ReviewListData>

    @GET("api/destinations/{id}/review-eligibility")
    suspend fun getReviewEligibility(
        @Header("Authorization") token: String,
        @Path("id") destinationId: Int
    ): ApiResponse<ReviewEligibility>

    @POST("api/bookings/{bookingId}/review")
    suspend fun submitReview(
        @Header("Authorization") token: String,
        @Path("bookingId") bookingId: Int,
        @Body request: ReviewRequest
    ): ApiResponse<Review>

    @PATCH("api/reviews/{reviewId}")
    suspend fun updateReview(
        @Header("Authorization") token: String,
        @Path("reviewId") reviewId: Int,
        @Body request: ReviewRequest
    ): ApiResponse<Review>

    @DELETE("api/reviews/{reviewId}")
    suspend fun deleteReview(
        @Header("Authorization") token: String,
        @Path("reviewId") reviewId: Int
    ): ApiResponse<Any>

    // Tour Package Endpoints
    @GET("api/tour-packages")
    suspend fun getTourPackages(
        @QueryMap query: Map<String, String>,
        @Header("Authorization") token: String? = null
    ): ApiResponse<List<TourPackage>>

    @GET("api/tour-packages/{id}")
    suspend fun getTourPackageDetail(
        @Path("id") id: Int,
        @Header("Authorization") token: String? = null
    ): ApiResponse<TourPackageDetail>

    // Per-date capacity, closed dates, and remaining seats. Public: the app
    // uses it to stop a traveller before they reach the booking endpoint.
    @GET("api/tour-packages/{id}/availability")
    suspend fun getAvailability(
        @Path("id") id: Int,
        @Query("month") month: String,
        @Query("people") people: Int = 1
    ): ApiResponse<AvailabilityMonth>

    @GET("api/tour-packages/destination/{destinationId}")
    suspend fun getDestinationTourPackages(
        @Path("destinationId") destinationId: Int,
        @Header("Authorization") token: String? = null
    ): ApiResponse<List<TourPackage>>

    // Wishlist / favourites (authenticated).
    @GET("api/wishlist")
    suspend fun getWishlist(
        @Header("Authorization") token: String,
        @Query("type") type: String? = null,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20
    ): ApiResponse<List<WishlistEntry>>

    @POST("api/wishlist/toggle")
    suspend fun toggleWishlist(
        @Header("Authorization") token: String,
        @Body request: WishlistToggleRequest
    ): ApiResponse<WishlistToggleData>

    @DELETE("api/wishlist/{id}")
    suspend fun deleteWishlist(
        @Header("Authorization") token: String,
        @Path("id") id: Int
    ): ApiResponse<Any>

    // Booking Endpoints
    @GET("api/my-bookings")
    suspend fun getMyBookings(
        @Header("Authorization") token: String,
        @Query("page") page: Int = 1
    ): ApiResponse<List<Booking>>

    // Server-validated promo preview. The booking endpoint revalidates the
    // code, so this response is display-only.
    @POST("api/promos/validate")
    suspend fun validatePromo(
        @Header("Authorization") token: String,
        @Body request: PromoValidateRequest
    ): ApiResponse<PromoPreview>

    @POST("api/bookings")
    suspend fun createBooking(
        @Header("Authorization") token: String,
        @Body request: BookingRequest
    ): ApiResponse<CreateBookingResponseData>

    @GET("api/bookings/{id}")
    suspend fun getBookingDetail(
        @Header("Authorization") token: String,
        @Path("id") id: Int
    ): ApiResponse<Booking>

    // Backend-verified e-ticket. Only issued for the owner of a paid,
    // confirmed booking; the QR token is signed by the server.
    @GET("api/bookings/{id}/e-ticket")
    suspend fun getETicket(
        @Header("Authorization") token: String,
        @Path("id") id: Int
    ): ApiResponse<ETicket>

    // Print-ready invoice HTML for a paid booking. Streamed because the body
    // is written to app cache before the user opens or shares it. Accept: JSON
    // keeps error responses (409/404) readable instead of an HTML error page.
    @Headers("Accept: application/json")
    @Streaming
    @GET("api/bookings/{id}/invoice/download")
    suspend fun downloadInvoice(
        @Header("Authorization") token: String,
        @Path("id") id: Int
    ): Response<ResponseBody>

    // Cancellation. Submitting is advisory: the booking only changes after an
    // admin approves the request.
    @GET("api/bookings/{id}/change-requests")
    suspend fun getChangeRequests(
        @Header("Authorization") token: String,
        @Path("id") bookingId: Int
    ): ApiResponse<List<BookingChangeRequest>>

    @POST("api/bookings/{id}/change-requests")
    suspend fun requestBookingChange(
        @Header("Authorization") token: String,
        @Path("id") bookingId: Int,
        @Body request: BookingChangeRequestInput
    ): ApiResponse<BookingChangeRequest>

    // Refund preview: estimated refund before submitting a cancellation request.
    @GET("api/bookings/{id}/refund-preview")
    suspend fun getRefundPreview(
        @Header("Authorization") token: String,
        @Path("id") bookingId: Int
    ): ApiResponse<RefundPreview>

    // Firebase Cloud Messaging device registration.
    @POST("api/device-tokens")
    suspend fun registerDeviceToken(
        @Header("Authorization") token: String,
        @Body request: DeviceTokenRequest
    ): ApiResponse<Any>

    @DELETE("api/device-tokens")
    suspend fun unregisterDeviceToken(
        @Header("Authorization") token: String,
        @Body request: DeviceTokenRequest
    ): ApiResponse<Any>

    // Visitor identity. Documents are multipart uploads into private
    // storage; the server returns document_status, never the raw file path.
    @GET("api/bookings/{id}/visitors")
    suspend fun getVisitors(
        @Header("Authorization") token: String,
        @Path("id") bookingId: Int
    ): ApiResponse<List<Visitor>>

    @Multipart
    @POST("api/bookings/{id}/visitors/{visitorId}/identity-document")
    suspend fun uploadVisitorDocument(
        @Header("Authorization") token: String,
        @Path("id") bookingId: Int,
        @Path("visitorId") visitorId: Int,
        @Part document: MultipartBody.Part
    ): ApiResponse<Visitor>

    // Payment Endpoints
    @POST("api/payments/create-snap-token")
    suspend fun createSnapToken(
        @Header("Authorization") token: String,
        @Body request: SnapTokenRequest
    ): ApiResponse<CreateBookingResponseData>

    @GET("api/bookings/{bookingId}/payment-status")
    suspend fun getPaymentStatus(
        @Header("Authorization") token: String,
        @Path("bookingId") bookingId: Int
    ): ApiResponse<PaymentStatusData>

    @POST("api/bookings/{bookingId}/payment/verify-and-sync")
    suspend fun verifyAndSyncPayment(
        @Header("Authorization") token: String,
        @Path("bookingId") bookingId: Int
    ): ApiResponse<PaymentSyncResponse>

    @POST("api/bookings/{bookingId}/payment/retry-sync")
    suspend fun retrySyncPayment(
        @Header("Authorization") token: String,
        @Path("bookingId") bookingId: Int
    ): ApiResponse<PaymentSyncResponse>

    @POST("api/bookings/{bookingId}/payment/mark-as-paid")
    suspend fun markPaymentAsPaid(
        @Header("Authorization") token: String,
        @Path("bookingId") bookingId: Int
    ): ApiResponse<PaymentSyncResponse>

    @POST("api/midtrans/callback")
    suspend fun midtransCallback(
        @Body webhookData: Map<String, Any>
    ): ApiResponse<Any>

    @POST("api/midtrans/notification")
    suspend fun midtransNotification(
        @Body webhookData: Map<String, Any>
    ): ApiResponse<Any>

    @GET("api/midtrans/health")
    suspend fun midtransHealth(): ApiResponse<Map<String, Any>>
}
