package com.naylaaisyah.traveloapp.data.models

import com.google.gson.annotations.SerializedName

// User Models
data class User(
    val id: Int,
    val name: String,
    val email: String,
    val phone: String? = null,
    // The backend stores this as `photo`; the UI keeps calling it avatar.
    @SerializedName("photo")
    val avatar: String? = null,
    @SerializedName("date_of_birth")
    val dateOfBirth: String? = null,
    val city: String? = null,
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

/**
 * Fields a traveller may change from Profile. Email is deliberately absent:
 * it is owned by the verified Firebase identity.
 */
data class UpdateProfileRequest(
    val name: String,
    val phone: String? = null,
    @SerializedName("date_of_birth")
    val dateOfBirth: String? = null,
    val city: String? = null
)

// Email OTP verification (login step 2). The 6-digit code is only ever sent
// by email — it never travels through the API in plaintext.
data class OtpSendResponse(
    val email: String? = null,
    @SerializedName("expires_in")
    val expiresIn: Int = 300,
    @SerializedName("resend_after")
    val resendAfter: Int = 60
)

data class OtpVerifyRequest(
    @SerializedName("id_token")
    val idToken: String,
    val code: String
)

// Destination Models
data class Destination(
    val id: Int,
    val name: String,
    val description: String? = null,
    val image: String? = null,
    val rating: Double? = null,
    @SerializedName("review_count")
    val reviewCount: Int = 0,
    val country: String,
    val city: String,
    val category: String? = null,
    @SerializedName("is_popular")
    val isPopular: Boolean = false,
    @SerializedName("is_wishlisted")
    val isWishlisted: Boolean = false,
    val latitude: Double? = null,
    val longitude: Double? = null,
    val images: List<DestinationImage> = emptyList(),
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
    @SerializedName("review_count")
    val reviewCount: Int = 0,
    @SerializedName("is_wishlisted")
    val isWishlisted: Boolean = false,
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
    @SerializedName("review_count")
    val reviewCount: Int = 0,
    val destination: Destination? = null,
    val inclusions: List<String> = emptyList(),
    val exclusions: List<String> = emptyList(),
    @SerializedName("meeting_point")
    val meetingPoint: String? = null,
    @SerializedName("meeting_latitude")
    val meetingLatitude: Double? = null,
    @SerializedName("meeting_longitude")
    val meetingLongitude: Double? = null,
    val terms: String? = null,
    val itinerary: List<ItineraryDay> = emptyList(),
    val images: List<TourPackageImage> = emptyList(),
    @SerializedName("is_wishlisted")
    val isWishlisted: Boolean = false,
    @SerializedName("created_at")
    val createdAt: String? = null
)

/** One day of a package itinerary, ordered by the backend. */
data class ItineraryDay(
    val id: Int? = null,
    @SerializedName("day_number")
    val dayNumber: Int = 0,
    val title: String = "",
    val description: String? = null,
    @SerializedName("sort_order")
    val sortOrder: Int = 0
) {
    val day: Int
        get() = dayNumber
}

/** A gallery photo returned by the API. `url` is ready for Coil. */
data class TourPackageImage(
    val id: Int? = null,
    val path: String? = null,
    val url: String? = null,
    val caption: String? = null,
    @SerializedName("sort_order")
    val sortOrder: Int = 0
)

data class DestinationImage(
    val id: Int? = null,
    val path: String? = null,
    val url: String? = null,
    val caption: String? = null,
    @SerializedName("sort_order")
    val sortOrder: Int = 0
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
    @SerializedName("subtotal_amount")
    val subtotalAmount: Double? = null,
    @SerializedName("discount_amount")
    val discountAmount: Double = 0.0,
    @SerializedName("promo_code")
    val promoCode: String? = null,
    val promo: PromoInfo? = null,
    @SerializedName("booking_status")
    val status: String,
    @SerializedName("payment_status")
    val paymentStatus: String? = null,
    // Server-authoritative payment window. Countdowns must use these fields,
    // never the device wall clock (the phone may be wrong on time/zone).
    @SerializedName("payment_expires_at")
    val paymentExpiresAt: String? = null,
    @SerializedName("payment_expires_in_seconds")
    val paymentExpiresInSeconds: Int? = null,
    @SerializedName("payment_expired")
    val paymentExpired: Boolean? = null,
    @SerializedName("server_time")
    val serverTime: String? = null,
    @SerializedName("ticket_usage_status")
    val ticketUsageStatus: String = "unused",
    @SerializedName("ticket_used_at")
    val ticketUsedAt: String? = null,
    @SerializedName("booking_date")
    val bookingDate: String,
    // Laravel snake-cases loaded relations, so the API key is `tour_package`.
    @SerializedName("tour_package")
    val tourPackage: TourPackage? = null,
    val payment: Payment? = null,
    val visitors: List<Visitor> = emptyList(),
    // Verified-visit review state for this booking. `review` only contains
    // the owner's safe review representation, never another user's ids.
    val review: Review? = null,
    @SerializedName("review_status")
    val reviewStatus: String? = null,
    @SerializedName("can_review")
    val canReview: Boolean? = null,
    // Newest cancellation request. The booking itself only changes after an
    // admin approves the request.
    @SerializedName("latest_change_request")
    val latestChangeRequest: BookingChangeRequest? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

/**
 * The promo attached to a booking. The discount snapshot lives on the booking
 * itself; this is only used to label the applied code.
 */
data class PromoInfo(
    val id: Int,
    val code: String,
    val description: String? = null,
    @SerializedName("discount_type")
    val discountType: String? = null
)

/**
 * Server-validated price preview. Clients display these amounts and never
 * calculate a discount locally.
 */
data class PromoPreview(
    @SerializedName("promo_code")
    val promoCode: String,
    val subtotal: Double,
    val discount: Double,
    val total: Double,
    @SerializedName("discount_type")
    val discountType: String? = null,
    @SerializedName("discount_value")
    val discountValue: Double? = null,
    @SerializedName("max_discount")
    val maxDiscount: Double? = null
)

data class PromoValidateRequest(
    val code: String,
    @SerializedName("tour_package_id")
    val tourPackageId: Int,
    val people: Int,
    val date: String
)

/**
 * A traveller-initiated cancellation request. `status` is the source of truth
 * for whether the booking has actually changed.
 */
data class BookingChangeRequest(
    val id: Int,
    @SerializedName("booking_id")
    val bookingId: Int,
    val type: String,
    val status: String,
    @SerializedName("original_booking_date")
    val originalBookingDate: String? = null,
    val reason: String? = null,
    @SerializedName("admin_note")
    val adminNote: String? = null,
    @SerializedName("refund_status")
    val refundStatus: String? = null,
    @SerializedName("refund_percentage")
    val refundPercentage: Int? = null,
    @SerializedName("refund_amount")
    val refundAmount: Double? = null,
    @SerializedName("refund_reference")
    val refundReference: String? = null,
    @SerializedName("refunded_at")
    val refundedAt: String? = null,
    @SerializedName("refund_failure_reason")
    val refundFailureReason: String? = null,
    @SerializedName("reviewed_at")
    val reviewedAt: String? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
) {
    val isPending: Boolean
        get() = status == STATUS_PENDING

    val isCancellation: Boolean
        get() = type == TYPE_CANCELLATION

    fun refundStatusLabel(): String {
        return when (refundStatus) {
            "not_applicable" -> "Not applicable"
            "pending" -> "Pending"
            "processing" -> "Processing"
            "refunded" -> "Refunded"
            "failed" -> "Failed"
            else -> refundStatus?.capitalize() ?: "Unknown"
        }
    }

    companion object {
        const val TYPE_CANCELLATION = "cancellation"

        const val STATUS_PENDING = "pending"
        const val STATUS_APPROVED = "approved"
        const val STATUS_REJECTED = "rejected"
        const val STATUS_CANCELLED = "cancelled"
    }
}

data class BookingChangeRequestInput(
    val type: String,
    val reason: String? = null
)

/**
 * Server-computed refund estimate shown before the traveller confirms a
 * cancellation request. Amounts come from the backend; the client only
 * formats them for display.
 */
data class RefundPreview(
    val bookingId: Int,
    @SerializedName("total_price")
    val totalPrice: Double,
    @SerializedName("discount_amount")
    val discountAmount: Double,
    val promoCode: String? = null,
    val percentage: Int,
    @SerializedName("refund_amount")
    val refundAmount: Double,
    val tier: String,
    @SerializedName("tier_label")
    val tierLabel: String,
    @SerializedName("days_before")
    val daysBefore: Int,
    val eligible: Boolean,
    val reason: String? = null
)

/** Per-date capacity/closed override returned by the availability endpoint. */
data class AvailabilityDay(
    val date: String,
    val capacity: Int = 0,
    val booked: Int = 0,
    val remaining: Int = 0,
    @SerializedName("is_closed")
    val isClosed: Boolean = false,
    val available: Boolean = true,
    val reason: String? = null,
    val note: String? = null,
    @SerializedName("has_override")
    val hasOverride: Boolean = false
)

data class AvailabilityMonth(
    @SerializedName("tour_package_id")
    val tourPackageId: Int,
    @SerializedName("default_capacity")
    val defaultCapacity: Int = 0,
    val month: String,
    val days: List<AvailabilityDay> = emptyList()
) {
    val byDate: Map<String, AvailabilityDay>
        get() = days.associateBy { it.date }
}

data class DeviceTokenRequest(
    val token: String,
    val platform: String = "android",
    @SerializedName("device_name")
    val deviceName: String? = null
)

// Verified-visit review models
data class Review(
    val id: Int,
    val rating: Int,
    val comment: String? = null,
    @SerializedName("reviewer_name")
    val reviewerName: String? = null,
    @SerializedName("verified_visit")
    val verifiedVisit: Boolean = false,
    @SerializedName("booking_id")
    val bookingId: Int? = null,
    val status: String? = null,
    @SerializedName("moderation_reason")
    val moderationReason: String? = null,
    val destination: ReviewDestination? = null,
    @SerializedName("created_at")
    val createdAt: String? = null,
    @SerializedName("updated_at")
    val updatedAt: String? = null
) {
    val isPublished: Boolean
        get() = status == null || status == "published"
}

data class ReviewDestination(
    val id: Int,
    val name: String,
    val city: String? = null,
    val country: String? = null
)

data class ReviewSummary(
    val average: Double? = null,
    val count: Int = 0,
    val distribution: Map<String, Int> = emptyMap()
)

data class ReviewMeta(
    @SerializedName("current_page")
    val currentPage: Int = 1,
    @SerializedName("last_page")
    val lastPage: Int = 1,
    @SerializedName("per_page")
    val perPage: Int = 5,
    val total: Int = 0
)

data class ReviewListData(
    val summary: ReviewSummary = ReviewSummary(),
    val reviews: List<Review> = emptyList(),
    val meta: ReviewMeta = ReviewMeta()
)

data class ReviewEligibility(
    @SerializedName("destination_id")
    val destinationId: Int,
    val status: String,
    @SerializedName("can_review")
    val canReview: Boolean = false,
    @SerializedName("booking_id")
    val bookingId: Int? = null,
    val review: Review? = null,
    val message: String? = null
)

data class ReviewRequest(
    val rating: Int,
    val comment: String? = null
)

/**
 * One traveller on a booking. The backend never sends the full identity
 * number or the private document path; only a masked number and a document
 * status are exposed.
 */
data class Visitor(
    val id: Int,
    val name: String,
    @SerializedName("is_booker")
    val isBooker: Boolean = false,
    @SerializedName("identity_type")
    val identityType: String? = null,
    @SerializedName("identity_type_other")
    val identityTypeOther: String? = null,
    @SerializedName("identity_type_label")
    val identityTypeLabel: String? = null,
    @SerializedName("identity_number_masked")
    val identityNumberMasked: String? = null,
    @SerializedName("date_of_birth")
    val dateOfBirth: String? = null,
    val phone: String? = null,
    @SerializedName("document_status")
    val documentStatus: String = "missing",
    @SerializedName("has_document")
    val hasDocument: Boolean = false,
    @SerializedName("is_identity_complete")
    val isIdentityComplete: Boolean = false
)

data class VisitorRequest(
    val name: String,
    @SerializedName("identity_type")
    val identityType: String,
    @SerializedName("identity_type_other")
    val identityTypeOther: String? = null,
    @SerializedName("identity_number")
    val identityNumber: String,
    @SerializedName("date_of_birth")
    val dateOfBirth: String? = null,
    val phone: String? = null,
    @SerializedName("is_booker")
    val isBooker: Boolean = false
)

/** Form values collected on the booking screen. */
data class VisitorInput(
    val name: String,
    val identityType: String,
    val identityTypeOther: String? = null,
    val identityNumber: String,
    val dateOfBirth: String? = null,
    val phone: String? = null
)

/** In-memory document bytes, uploaded after the booking gets an id. */
data class VisitorDocumentInput(
    val fileName: String,
    val mimeType: String,
    val bytes: ByteArray
)

/**
 * The booker's account-level identity, used to prefill later bookings.
 * Identity numbers are returned only to the authenticated owner; the private
 * document path is never included.
 */
data class SavedIdentity(
    val name: String,
    @SerializedName("identity_type")
    val identityType: String,
    @SerializedName("identity_type_other")
    val identityTypeOther: String? = null,
    @SerializedName("identity_number")
    val identityNumber: String,
    @SerializedName("date_of_birth")
    val dateOfBirth: String? = null,
    val phone: String? = null,
    @SerializedName("has_document")
    val hasDocument: Boolean = false,
    @SerializedName("can_reuse_document")
    val canReuseDocument: Boolean = false,
    @SerializedName("document_status")
    val documentStatus: String? = null,
    @SerializedName("document_uploaded_at")
    val documentUploadedAt: String? = null,
    @SerializedName("updated_at")
    val updatedAt: String? = null
)

data class TicketDestination(
    val id: Int? = null,
    val name: String? = null,
    val city: String? = null
)


/**
 * Backend-issued e-ticket. The reference and signed token are created only
 * after the API verifies ownership and a confirmed payment; the app must never
 * derive a ticket from a local booking id.
 */
data class ETicket(
    val reference: String,
    @SerializedName("ticket_token")
    val ticketToken: String,
    @SerializedName("issued_at")
    val issuedAt: String? = null,
    @SerializedName("booking_id")
    val bookingId: Int,
    @SerializedName("tour_title")
    val tourTitle: String? = null,
    @SerializedName("booking_date")
    val bookingDate: String? = null,
    @SerializedName("people_count")
    val peopleCount: Int? = null,
    @SerializedName("holder_name")
    val holderName: String? = null,
    @SerializedName("subtotal_amount")
    val subtotalAmount: Double? = null,
    @SerializedName("discount_amount")
    val discountAmount: Double = 0.0,
    @SerializedName("promo_code")
    val promoCode: String? = null,
    @SerializedName("total_price")
    val totalPrice: Double? = null,
    @SerializedName("usage_status")
    val usageStatus: String = "unused",
    @SerializedName("payment_status")
    val paymentStatus: String? = null,
    @SerializedName("valid_until")
    val validUntil: String? = null,
    val destination: TicketDestination? = null,
    val visitors: List<Visitor> = emptyList()
)

data class BookingRequest(
    @SerializedName("tour_package_id")
    val tourPackageId: Int,
    @SerializedName("people_count")
    val peopleCount: Int,
    @SerializedName("booking_date")
    val bookingDate: String,
    @SerializedName("promo_code")
    val promoCode: String? = null,
    @SerializedName("special_requests")
    val specialRequests: String? = null,
    val visitors: List<VisitorRequest>? = null,
    @SerializedName("privacy_consent")
    val privacyConsent: Boolean? = null,
    @SerializedName("reuse_saved_identity_document")
    val reuseSavedIdentityDocument: Boolean? = null
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
    @SerializedName("channel")
    val channel: String? = null,
    @SerializedName("transaction_status")
    val transactionStatus: String? = null,
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
    // Server-anchored expiry/countdown fields. payment_expired is true once
    // the backend deadline passed, even before the scheduler cancels the row.
    @SerializedName("payment_expires_at")
    val paymentExpiresAt: String? = null,
    @SerializedName("payment_expires_in_seconds")
    val paymentExpiresInSeconds: Int? = null,
    @SerializedName("payment_expired")
    val paymentExpired: Boolean? = null,
    @SerializedName("server_time")
    val serverTime: String? = null,
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
    // Nullable: backend sends null until the user picks a method in Snap.
    @SerializedName("payment_method")
    val paymentMethod: String? = null,
    @SerializedName("payment_channel")
    val paymentChannel: String? = null,
    @SerializedName("transaction_status")
    val transactionStatus: String? = null,
    @SerializedName("midtrans_transaction_id")
    val transactionId: String? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

/** Unified, user-facing payment state derived from backend truth. */
enum class PaymentOutcome {
    SUCCESS, WAITING, FAILED, EXPIRED, CANCELLED
}

fun Payment.outcome(): PaymentOutcome {
    val raw = transactionStatus?.lowercase()
    if (raw == "cancel") return PaymentOutcome.CANCELLED
    return when (status.lowercase()) {
        "paid", "success", "settlement" -> PaymentOutcome.SUCCESS
        "expired", "expire" -> PaymentOutcome.EXPIRED
        "failed", "deny", "cancel" -> PaymentOutcome.FAILED
        else -> PaymentOutcome.WAITING
    }
}

fun Booking.paymentOutcome(): PaymentOutcome {
    // Backend-confirmed success always wins, even if the payment relation was
    // not included (e.g. a partial list response).
    if (status.lowercase() == "confirmed" || paymentStatus?.lowercase() == "paid") {
        return PaymentOutcome.SUCCESS
    }

    val local = paymentStatus?.lowercase()

    if (local == "expired" || local == "expire") return PaymentOutcome.EXPIRED

    if (local == "failed" || local == "deny" || local == "cancel") {
        val raw = payment?.transactionStatus?.lowercase()
        return if (raw == "cancel") PaymentOutcome.CANCELLED else PaymentOutcome.FAILED
    }

    return payment?.outcome() ?: PaymentOutcome.WAITING
}

/**
 * Cancellation is only meaningful for a paid, confirmed, unused ticket with no
 * request already waiting for review. The backend re-checks all of this,
 * including the visit date, before accepting a request.
 */
fun Booking.canRequestCancellation(): Boolean {
    return paymentStatus?.lowercase() == "paid" &&
        status.lowercase() == "confirmed" &&
        ticketUsageStatus.lowercase() == "unused" &&
        latestChangeRequest?.isPending != true
}

fun PaymentStatusData.outcome(): PaymentOutcome {
    val raw = payment?.transactionStatus?.lowercase()
    val ps = (paymentStatus ?: status ?: payment?.status)?.lowercase()
    val bs = (bookingStatus ?: bookingStatusAlt)?.lowercase()
    if (bs == "confirmed" || ps == "paid" || ps == "success" || ps == "settlement") {
        return PaymentOutcome.SUCCESS
    }
    // The deadline check is authoritative even when the scheduler has not yet
    // moved the row to expired/cancelled.
    if (paymentExpired == true) return PaymentOutcome.EXPIRED
    if (ps == "expired" || ps == "expire") return PaymentOutcome.EXPIRED
    if (ps == "failed" || ps == "deny" || ps == "cancel") {
        return if (raw == "cancel") PaymentOutcome.CANCELLED else PaymentOutcome.FAILED
    }
    return PaymentOutcome.WAITING
}

/** Friendly payment method label, e.g. "GoPay", "QRIS", "BCA Virtual Account". */
fun Payment.displayMethod(): String {
    val method = (paymentMethod ?: "").lowercase()
    val channel = (paymentChannel ?: "").lowercase()
    return when (method) {
        "credit_card" -> "Credit/Debit Card"
        "qris" -> "QRIS"
        "gopay" -> "GoPay"
        "shopeepay" -> "ShopeePay"
        "ovo" -> "OVO"
        "dana" -> "DANA"
        "linkaja" -> "LinkAja"
        "bank_transfer" -> if (channel.isNotEmpty()) "${channel.uppercase()} Virtual Account" else "Bank Transfer"
        "echannel" -> "Mandiri Bill"
        "bca_klikpay" -> "BCA KlikPay"
        "bri_epay" -> "BRI ePay"
        "cimb_clicks" -> "CIMB Clicks"
        "danamon_online" -> "Danamon Online"
        "akulaku" -> "Akulaku"
        "kredivo" -> "Kredivo"
        "uob_ezpay" -> "UOB eZPay"
        "cstore" -> if (channel.isNotEmpty()) {
            "Gerai ${channel.replaceFirstChar { it.uppercase() }}"
        } else {
            "Gerai Retail"
        }
        else -> if (method.isNotEmpty()) method else "Payment"
    }
}

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
    // Top-level pagination block returned by the catalog list endpoints.
    val meta: CatalogMeta? = null,
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

// Wishlist / favourites. Item summaries are produced by the backend so the
// app never has to resolve a saved id against a stale local catalog.
object WishlistTypes {
    const val DESTINATION = "destination"
    const val TOUR_PACKAGE = "tour_package"
}

data class WishlistItem(
    val id: Int,
    val type: String,
    val name: String,
    val city: String? = null,
    val country: String? = null,
    val image: String? = null,
    val price: Double? = null,
    @SerializedName("price_formatted")
    val priceFormatted: String? = null,
    val rating: Double? = null,
    @SerializedName("review_count")
    val reviewCount: Int = 0,
    @SerializedName("duration_days")
    val durationDays: Int? = null
)

data class WishlistEntry(
    val id: Int,
    @SerializedName("item_type")
    val itemType: String,
    @SerializedName("item_id")
    val itemId: Int,
    val item: WishlistItem? = null,
    @SerializedName("created_at")
    val createdAt: String? = null
)

data class WishlistToggleRequest(
    @SerializedName("item_type")
    val itemType: String,
    @SerializedName("item_id")
    val itemId: Int
)

data class WishlistToggleData(
    @SerializedName("is_wishlisted")
    val isWishlisted: Boolean
)
