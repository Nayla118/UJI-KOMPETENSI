package com.naylaaisyah.traveloapp.data.api

import com.naylaaisyah.traveloapp.data.models.*
import okhttp3.MultipartBody
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

    // Destination Endpoints
    @GET("api/destinations")
    suspend fun getDestinations(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10
    ): ApiResponse<List<Destination>>

    @GET("api/destinations/popular")
    suspend fun getPopularDestinations(
        @Query("limit") limit: Int = 6
    ): ApiResponse<List<Destination>>

    @GET("api/destinations/{id}")
    suspend fun getDestinationDetail(
        @Path("id") id: Int
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
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10
    ): ApiResponse<List<TourPackage>>

    @GET("api/tour-packages/{id}")
    suspend fun getTourPackageDetail(
        @Path("id") id: Int
    ): ApiResponse<TourPackageDetail>

    @GET("api/tour-packages/destination/{destinationId}")
    suspend fun getDestinationTourPackages(
        @Path("destinationId") destinationId: Int
    ): ApiResponse<List<TourPackage>>

    // Booking Endpoints
    @GET("api/my-bookings")
    suspend fun getMyBookings(
        @Header("Authorization") token: String,
        @Query("page") page: Int = 1
    ): ApiResponse<List<Booking>>

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
