package com.naylaaisyah.traveloapp.data.tickets

import com.naylaaisyah.traveloapp.data.models.Booking

/**
 * Detects the exact transition the traveller needs to know about: a ticket
 * that was active the last time My Bookings loaded is now marked used by the
 * backend.
 *
 * The previous active ids come from a local snapshot, never from a local
 * timer or a guessed status. First launch (no snapshot) must not announce
 * old history as a fresh scan.
 */
object TicketUsageNoticeRules {

    fun activeIds(bookings: List<Booking>): Set<Int> {
        return TicketHistoryRules.current(bookings).map { it.id }.toSet()
    }

    fun newlyUsed(bookings: List<Booking>, previousActiveIds: Set<Int>): Booking? {
        if (previousActiveIds.isEmpty()) {
            return null
        }

        return bookings.firstOrNull { booking ->
            booking.id in previousActiveIds &&
                booking.ticketUsageStatus.equals("used", ignoreCase = true)
        }
    }
}
