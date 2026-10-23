package com.naylaaisyah.traveloapp.ui.screens.profile

import com.naylaaisyah.traveloapp.data.models.Booking
import java.time.LocalDate

/**
 * Small, testable profile facts. Keeping these out of the composables makes
 * the "profile strength" and "next trip" rules deterministic and prevents the
 * screen from inventing values that the backend does not return.
 */
internal data class ProfileCompletion(
    val percent: Int,
    val missingLabels: List<String>,
    val missingPersonalLabels: List<String>,
    val missingSavedIdentity: Boolean
)

internal fun calculateProfileCompletion(
    name: String?,
    email: String?,
    phone: String?,
    dateOfBirth: String?,
    city: String?,
    hasSavedIdentity: Boolean
): ProfileCompletion {
    val checks = listOf(
        "name" to !name.isNullOrBlank(),
        "email" to !email.isNullOrBlank(),
        "phone number" to !phone.isNullOrBlank(),
        "date of birth" to !dateOfBirth.isNullOrBlank(),
        "home city" to !city.isNullOrBlank(),
        "saved identity" to hasSavedIdentity
    )

    val completed = checks.count { it.second }
    val percent = (completed * 100) / checks.size

    val missingPersonal = checks
        .filterNot { it.second }
        .map { it.first }
        .filterNot { it == "saved identity" }

    return ProfileCompletion(
        percent = percent,
        missingLabels = checks.filterNot { it.second }.map { it.first },
        missingPersonalLabels = missingPersonal,
        missingSavedIdentity = checks.any { it.first == "saved identity" && !it.second }
    )
}

internal fun joinProfileLabels(labels: List<String>): String = when (labels.size) {
    0 -> ""
    1 -> labels.first()
    2 -> "${labels[0]} and ${labels[1]}"
    else -> labels.dropLast(1).joinToString(", ") + ", and " + labels.last()
}

/**
 * The next trip is the earliest non-cancelled booking whose visit date is
 * today or later. Used/expired bookings are naturally excluded by the date.
 */
internal fun nextUpcomingBooking(
    bookings: List<Booking>,
    todayIso: String = LocalDate.now().toString()
): Booking? {
    return bookings
        .filterNot { it.status.equals("cancelled", ignoreCase = true) }
        .filter { it.bookingDate.take(10) >= todayIso }
        .minByOrNull { it.bookingDate.take(10) }
}

internal enum class ProfileTripTone { Neutral, Success, Warning, Danger }

internal data class ProfileTripStatus(
    val label: String,
    val tone: ProfileTripTone
)

/**
 * Reads the payment status first because that is the source of truth for
 * whether a traveller can use the ticket. Unknown states fall back to a
 * readable version of the booking status instead of being hidden.
 */
internal fun profileTripStatus(booking: Booking): ProfileTripStatus {
    val bookingStatus = booking.status.trim().lowercase()
    val paymentStatus = booking.paymentStatus?.trim()?.lowercase()

    if (bookingStatus == "cancelled" || bookingStatus == "canceled") {
        return ProfileTripStatus("Cancelled", ProfileTripTone.Danger)
    }

    return when (paymentStatus) {
        "settlement", "capture", "paid", "success", "confirmed" ->
            ProfileTripStatus("Paid", ProfileTripTone.Success)

        "pending" ->
            ProfileTripStatus("Payment pending", ProfileTripTone.Warning)

        "expire", "expired" ->
            ProfileTripStatus("Payment expired", ProfileTripTone.Warning)

        "deny", "denied", "cancel", "cancelled", "canceled", "failure", "failed" ->
            ProfileTripStatus("Payment failed", ProfileTripTone.Danger)

        else -> when {
            bookingStatus.contains("confirm") || bookingStatus == "paid" ->
                ProfileTripStatus("Confirmed", ProfileTripTone.Success)

            bookingStatus.contains("pending") ->
                ProfileTripStatus("Pending", ProfileTripTone.Warning)

            bookingStatus.isBlank() ->
                ProfileTripStatus("Booked", ProfileTripTone.Neutral)

            else ->
                ProfileTripStatus(
                    bookingStatus.replaceFirstChar { it.uppercase() },
                    ProfileTripTone.Neutral
                )
        }
    }
}
