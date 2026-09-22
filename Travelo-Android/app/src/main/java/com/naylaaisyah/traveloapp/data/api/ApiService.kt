package com.naylaaisyah.traveloapp.data.api

import com.naylaaisyah.traveloapp.data.models.*
import retrofit2.http.*

interface ApiService {
    // Authentication Endpoints
    @POST("api/auth/login")
    suspend fun login(@Body request: FirebaseLoginRequest): ApiResponse<AuthResponse>

    @POST("api/auth/register")
    suspend fun register(@Body request: RegisterRequest): ApiResponse<AuthResponse>

    @POST("api/auth/logout")
    suspend fun logout(@Header("Authorization") token: String): ApiResponse<Any>

    @GET("api/auth/me")
    suspend fun getUserProfile(
        @Header("Authorization") token: String
    ): ApiResponse<User>

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
}
