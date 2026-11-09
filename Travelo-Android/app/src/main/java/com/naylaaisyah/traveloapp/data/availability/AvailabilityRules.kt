package com.naylaaisyah.traveloapp.data.availability

import com.naylaaisyah.traveloapp.data.models.AvailabilityDay

/**
 * Client-side reading of the server's per-date availability.
 *
 * A missing day means "not loaded yet"; the UI must not block a booking in
 * that case because the server re-validates capacity inside the booking
 * transaction. When a day is known, closed dates and dates without enough
 * seats are blocked before the request is sent.
 */
object AvailabilityRules {

    fun monthOf(date: String): String? {
        if (date.length < 7 || date[4] != '-') return null
        return date.take(7)
    }

    fun isBookable(day: AvailabilityDay?, people: Int): Boolean {
        if (day == null) return true
        if (day.isClosed) return false
        return day.remaining >= people.coerceAtLeast(1)
    }

    fun message(day: AvailabilityDay?, people: Int): String? {
        if (day == null) return null

        val travelers = people.coerceAtLeast(1)

        return when {
            day.isClosed -> "This date is closed for this tour package. Choose another date."
            day.remaining <= 0 -> "This date is fully booked. Choose another date."
            day.remaining < travelers -> {
                val seats = if (day.remaining == 1) "1 seat" else "${day.remaining} seats"
                "Only $seats left on this date. Reduce travelers or choose another date."
            }
            day.remaining == 1 -> "1 seat left on this date."
            else -> "${day.remaining} seats left on this date."
        }
    }
}
