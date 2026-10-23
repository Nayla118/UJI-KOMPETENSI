package com.naylaaisyah.traveloapp.data.tickets

import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Payment
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * A scanned ticket must leave the active list and stay discoverable in history.
 * The backend's ticket_usage_status is the only usage input.
 */
class TicketHistoryRulesTest {

    private fun booking(
        id: Int = 42,
        usageStatus: String = "unused",
        bookingStatus: String = "confirmed",
        paymentStatus: String = "paid",
        payment: Payment? = Payment(
            id = 1,
            bookingId = 42,
            amount = 2_000_000.0,
            status = "paid"
        ),
        usedAt: String? = null
    ) = Booking(
        id = id,
        userId = 7,
        tourPackageId = 3,
        peopleCount = 2,
        totalPrice = 2_000_000.0,
        status = bookingStatus,
        paymentStatus = paymentStatus,
        ticketUsageStatus = usageStatus,
        ticketUsedAt = usedAt,
        bookingDate = "2026-10-21",
        payment = payment
    )

    @Test
    fun redeemed_ticket_moves_from_active_to_history() {
        val used = booking(
            usageStatus = "used",
            usedAt = "2026-10-21T09:30:00+07:00"
        )

        assertTrue(TicketHistoryRules.isHistoryTicket(used))
        assertTrue(TicketHistoryRules.current(listOf(used)).isEmpty())
        assertEquals(listOf(used), TicketHistoryRules.history(listOf(used)))
    }

    @Test
    fun paid_ticket_whose_visit_date_passed_is_history() {
        val expired = booking(usageStatus = "expired")

        assertTrue(TicketHistoryRules.isHistoryTicket(expired))
        assertEquals(listOf(expired), TicketHistoryRules.history(listOf(expired)))
    }

    @Test
    fun unused_paid_ticket_stays_active() {
        val active = booking(usageStatus = "unused")

        assertFalse(TicketHistoryRules.isHistoryTicket(active))
        assertEquals(listOf(active), TicketHistoryRules.current(listOf(active)))
        assertTrue(TicketHistoryRules.history(listOf(active)).isEmpty())
    }

    @Test
    fun unpaid_booking_with_a_past_date_is_not_a_ticket_history_item() {
        val unpaidExpired = booking(
            usageStatus = "expired",
            bookingStatus = "pending",
            paymentStatus = "pending",
            payment = null
        )

        assertFalse(TicketHistoryRules.isHistoryTicket(unpaidExpired))
        assertEquals(listOf(unpaidExpired), TicketHistoryRules.current(listOf(unpaidExpired)))
    }

    @Test
    fun current_and_history_split_preserves_every_booking_once() {
        val active = booking(id = 1, usageStatus = "unused")
        val used = booking(id = 2, usageStatus = "used")
        val expired = booking(id = 3, usageStatus = "expired")
        val unpaid = booking(
            id = 4,
            usageStatus = "unused",
            bookingStatus = "pending",
            paymentStatus = "pending",
            payment = null
        )
        val all = listOf(active, used, expired, unpaid)

        val current = TicketHistoryRules.current(all)
        val history = TicketHistoryRules.history(all)

        assertEquals(listOf(active, unpaid), current)
        assertEquals(listOf(used, expired), history)
        assertEquals(all.size, current.size + history.size)
    }
}
