package com.naylaaisyah.traveloapp.data.models

import com.google.gson.annotations.SerializedName

// User Models
data class User(
    val id: Int,
    val name: String,
    val email: String,
    val phone: String? = null,
    val avatar: String? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

val User.photo: String?
    get() = avatar

data class FirebaseLoginRequest(
    @SerializedName("id_token")
    val idToken: String
)

data class RegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    @SerializedName("password_confirmation")
    val passwordConfirmation: String
)

data class AuthResponse(
    val message: String,
    val token: String? = null,
    val user: User? = null
)

// Destination Models
data class Destination(
    val id: Int,
    val name: String,
    val description: String? = null,
    val image: String? = null,
    val rating: Double? = null,
    val country: String,
    val city: String,
    @SerializedName("is_popular")
    val isPopular: Boolean = false,
    @SerializedName("created_at")
    val createdAt: String? = null
)

val Destination.safeName: String
    get() = name

val Destination.safeCity: String
    get() = city

val Destination.safeCountry: String
    get() = country

val Destination.safeDescription: String
    get() = description.orEmpty()

val Destination.safeRating: Double
    get() = rating ?: 0.0

// Tour Package Models
data class TourPackage(
    val id: Int,
    val title: String,
    val description: String? = null,
    val image: String? = null,
    val price: Int,
    @SerializedName("duration_days")
    val durationDays: Int,
    @SerializedName("destination_id")
    val destinationId: Int,
    @SerializedName("max_people")
    val maxPeople: Int,
    val rating: Double? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

val TourPackage.safeName: String
    get() = title

val TourPackage.safeTitle: String
    get() = title

val TourPackage.safeDescription: String
    get() = description.orEmpty()

val TourPackage.safePrice: Double
    get() = price.toDouble()

val TourPackage.safeRating: Double
    get() = rating ?: 0.0

val TourPackage.duration: String
    get() = "$durationDays Days"

data class TourPackageDetail(
    val id: Int,
    val title: String,
    val description: String? = null,
    val image: String? = null,
    val price: Int,
    @SerializedName("duration_days")
    val durationDays: Int,
    @SerializedName("max_people")
    val maxPeople: Int,
    val rating: Double? = null,
    val destination: Destination? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

data class ItineraryDay(
    val day: Int,
    val title: String,
    val description: String,
    val activities: List<String>? = null
)

// Booking Models
data class Booking(
    val id: Int,
    @SerializedName("user_id")
    val userId: Int,
    @SerializedName("tour_package_id")
    val tourPackageId: Int,
    @SerializedName("people_count")
    val peopleCount: Int,
    @SerializedName("total_price")
    val totalPrice: Double,
    @SerializedName("booking_status")
    val status: String,
    @SerializedName("booking_date")
    val bookingDate: String,
    val tourPackage: TourPackage? = null,
    val payment: Payment? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

data class BookingRequest(
    @SerializedName("tour_package_id")
    val tourPackageId: Int,
    @SerializedName("people_count")
    val peopleCount: Int,
    @SerializedName("booking_date")
    val bookingDate: String,
    @SerializedName("special_requests")
    val specialRequests: String? = null
)

data class BookingResponse(
    val message: String,
    val booking: Booking? = null
)

data class CreateBookingResponseData(
    val booking: Booking,
    @SerializedName("snap_token")
    val snapToken: String? = null
)

data class SnapTokenRequest(
    @SerializedName("booking_id")
    val bookingId: Int
)

typealias CreateBookingResponse = ApiResponse<CreateBookingResponseData>

data class PaymentSyncResponse(
    @SerializedName("payment_status")
    val paymentStatus: String? = null,
    @SerializedName("booking_status")
    val bookingStatus: String? = null,
    val booking: Booking? = null,
    @SerializedName("booking_id")
    val bookingId: Int? = null,
    val status: String? = null,
    @SerializedName("bookingStatus")
    val bookingStatusAlt: String? = null
) {
    val effectivePaymentStatus: String?
        get() = paymentStatus ?: status
    val effectiveBookingStatus: String?
        get() = bookingStatus ?: bookingStatusAlt
}

data class PaymentStatusInfo(
    val id: Int? = null,
    val status: String? = null,
    val method: String? = null,
    @SerializedName("payment_method")
    val paymentMethodAlt: String? = null,
    val amount: Double? = null,
    @SerializedName("midtrans_transaction_id")
    val transactionId: String? = null,
    @SerializedName("midtrans_order_id")
    val orderId: String? = null,
    @SerializedName("paid_at")
    val paidAt: String? = null,
    @SerializedName("updated_at")
    val updatedAt: String? = null
)

data class PaymentStatusData(
    @SerializedName("booking_id")
    val bookingId: Int? = null,
    @SerializedName("booking_status")
    val bookingStatus: String? = null,
    @SerializedName("payment_status")
    val paymentStatus: String? = null,
    @SerializedName("total_price")
    val totalPrice: Double? = null,
    @SerializedName("bookingStatus")
    val bookingStatusAlt: String? = null,
    val status: String? = null,
    val payment: PaymentStatusInfo? = null
)

// Payment Models
data class Payment(
    val id: Int,
    @SerializedName("booking_id")
    val bookingId: Int,
    val amount: Double,
    @SerializedName("payment_status")
    val status: String,
    @SerializedName("payment_method")
    val paymentMethod: String,
    @SerializedName("midtrans_transaction_id")
    val transactionId: String? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

data class PaymentRequest(
    val booking_id: Int,
    val payment_method: String,
    val amount: Int
)

// API Response Wrapper
data class ApiResponse<T>(
    val success: Boolean,
    val message: String = "",
    val data: T? = null,
    val errors: Map<String, List<String>>? = null
)

// List Response
data class ListResponse<T>(
    val data: List<T>,
    val pagination: Pagination? = null
)

data class Pagination(
    val current_page: Int,
    val per_page: Int,
    val total: Int,
    val last_page: Int
)
