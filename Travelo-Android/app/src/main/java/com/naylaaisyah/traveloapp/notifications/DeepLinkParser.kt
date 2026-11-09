package com.naylaaisyah.traveloapp.notifications

import java.util.Locale

/**
 * A notification destination, independent of Android and Navigation Compose so
 * the mapping can be unit-tested without a device.
 */
sealed class DeepLinkTarget {
    data class BookingDetail(val bookingId: Int) : DeepLinkTarget()

    data class ETicket(val bookingId: Int) : DeepLinkTarget()

    data class Review(val destinationId: Int, val reviewId: Int?) : DeepLinkTarget()

    data class Destination(val destinationId: Int) : DeepLinkTarget()
}

/**
 * Converts FCM extras into a navigation target.
 *
 * The backend is the source of truth for `type` and `screen`, but the parser is
 * intentionally tolerant of older payloads: a known type still works without
 * `screen`, and a known screen still works when a newer type is unknown.
 * Incomplete or malformed ids return `null` instead of navigating to `0`.
 */
object DeepLinkParser {
    const val EXTRA_TYPE = "type"
    const val EXTRA_SCREEN = "screen"
    const val EXTRA_ID = "id"
    const val EXTRA_BOOKING_ID = "booking_id"
    const val EXTRA_DESTINATION_ID = "destination_id"
    const val EXTRA_REVIEW_ID = "review_id"

    const val SCREEN_BOOKING_DETAIL = "booking_detail"
    const val SCREEN_E_TICKET = "e_ticket"
    const val SCREEN_REVIEW = "review"
    const val SCREEN_DESTINATION = "destination"

    const val TYPE_PAYMENT_PAID = "payment_paid"
    const val TYPE_PAYMENT_FAILED = "payment_failed"
    const val TYPE_PAYMENT_EXPIRED = "payment_expired"
    const val TYPE_PAYMENT_AFTER_EXPIRY = "payment_after_expiry"
    const val TYPE_PAYMENT_UPDATE = "payment_update"
    const val TYPE_BOOKING_EXPIRED = "booking_expired"
    const val TYPE_TICKET_USED = "ticket_used"
    const val TYPE_CHANGE_REQUEST_APPROVED = "change_request_approved"
    const val TYPE_CHANGE_REQUEST_REJECTED = "change_request_rejected"
    const val TYPE_CHANGE_REQUEST_PENDING = "change_request_pending"
    const val TYPE_CHANGE_REQUEST_UPDATED = "change_request_updated"
    const val TYPE_REVIEW_MODERATED = "review_moderated"
    const val TYPE_TRIP_REMINDER = "trip_reminder"

    fun parse(extras: Map<String, String>): DeepLinkTarget? {
        val type = extras.value(EXTRA_TYPE)
        val screen = extras.value(EXTRA_SCREEN)
        val bookingId = extras.positiveInt(EXTRA_BOOKING_ID)
        val destinationId = extras.positiveInt(EXTRA_DESTINATION_ID)
        val reviewId = extras.positiveInt(EXTRA_REVIEW_ID)
        // Some clients may send a single generic id instead of a named one.
        val fallbackId = extras.positiveInt(EXTRA_ID)

        // An explicit, recognised screen wins. It lets a future notification type
        // deep-link correctly without an app update.
        when (screen) {
            SCREEN_BOOKING_DETAIL -> return (bookingId ?: fallbackId)
                ?.let { DeepLinkTarget.BookingDetail(it) }

            SCREEN_E_TICKET -> return (bookingId ?: fallbackId)
                ?.let { DeepLinkTarget.ETicket(it) }

            SCREEN_REVIEW -> return reviewTarget(destinationId, reviewId)

            SCREEN_DESTINATION -> return destinationId
                ?.let { DeepLinkTarget.Destination(it) }
        }

        return when (type) {
            TYPE_PAYMENT_PAID,
            TYPE_TICKET_USED,
            TYPE_TRIP_REMINDER -> (bookingId ?: fallbackId)
                ?.let { DeepLinkTarget.ETicket(it) }

            TYPE_PAYMENT_FAILED,
            TYPE_PAYMENT_EXPIRED,
            TYPE_PAYMENT_AFTER_EXPIRY,
            TYPE_PAYMENT_UPDATE,
            TYPE_BOOKING_EXPIRED,
            TYPE_CHANGE_REQUEST_APPROVED,
            TYPE_CHANGE_REQUEST_REJECTED,
            TYPE_CHANGE_REQUEST_PENDING,
            TYPE_CHANGE_REQUEST_UPDATED -> (bookingId ?: fallbackId)
                ?.let { DeepLinkTarget.BookingDetail(it) }

            TYPE_REVIEW_MODERATED -> reviewTarget(destinationId, reviewId)

            else -> null
        }
    }

    private fun reviewTarget(destinationId: Int?, reviewId: Int?): DeepLinkTarget? =
        destinationId?.let { DeepLinkTarget.Review(it, reviewId) }

    private fun Map<String, String>.value(key: String): String =
        this[key]?.trim()?.lowercase(Locale.ROOT).orEmpty()

    private fun Map<String, String>.positiveInt(key: String): Int? =
        this[key]
            ?.trim()
            ?.toIntOrNull()
            ?.takeIf { it > 0 }
}
