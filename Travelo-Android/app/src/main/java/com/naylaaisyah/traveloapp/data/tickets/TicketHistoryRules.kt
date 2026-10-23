package com.naylaaisyah.traveloapp.data.tickets

import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.paymentOutcome

/**
 * A used ticket is finished business: it belongs in Ticket history and must
 * never offer an active e-ticket action again.
 *
 * The backend remains the authority for both fields used here. The app does
 * not invent a usage state from the local booking id or the current clock.
 */
object TicketHistoryRules {

    fun isHistoryTicket(booking: Booking): Boolean {
        return when (booking.ticketUsageStatus.lowercase()) {
            "used" -> true
            "expired" -> booking.paymentOutcome() == PaymentOutcome.SUCCESS
            else -> false
        }
    }

    fun current(bookings: List<Booking>): List<Booking> {
        return bookings.filterNot(::isHistoryTicket)
    }

    fun history(bookings: List<Booking>): List<Booking> {
        return bookings.filter(::isHistoryTicket)
    }
}
