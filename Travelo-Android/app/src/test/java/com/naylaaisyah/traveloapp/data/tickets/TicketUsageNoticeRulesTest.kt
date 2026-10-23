package com.naylaaisyah.traveloapp.data.tickets

import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Payment
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class TicketUsageNoticeRulesTest {

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
        )
    ) = Booking(
        id = id,
        userId = 7,
        tourPackageId = 3,
        peopleCount = 2,
        totalPrice = 2_000_000.0,
        status = bookingStatus,
        paymentStatus = paymentStatus,
        ticketUsageStatus = usageStatus,
        bookingDate = "2026-10-21",
        payment = payment
    )

    @Test
    fun active_ticket_becomes_used_after_a_gate_scan() {
        val used = booking(usageStatus = "used")

        assertEquals(used, TicketUsageNoticeRules.newlyUsed(listOf(used), setOf(42)))
    }

    @Test
    fun old_history_is_not_announced_on_first_load() {
        val used = booking(usageStatus = "used")

        assertNull(TicketUsageNoticeRules.newlyUsed(listOf(used), emptySet()))
    }

    @Test
    fun ticket_that_is_still_active_does_not_raise_a_notice() {
        val active = booking(usageStatus = "unused")

        assertNull(TicketUsageNoticeRules.newlyUsed(listOf(active), setOf(42)))
    }

    @Test
    fun expired_ticket_is_not_a_scan_notice() {
        val expired = booking(usageStatus = "expired")

        assertNull(TicketUsageNoticeRules.newlyUsed(listOf(expired), setOf(42)))
    }

    @Test
    fun snapshot_only_contains_tickets_that_are_still_active() {
        val active = booking(id = 1, usageStatus = "unused")
        val used = booking(id = 2, usageStatus = "used")
        val expired = booking(id = 3, usageStatus = "expired")

        assertEquals(
            setOf(1),
            TicketUsageNoticeRules.activeIds(listOf(active, used, expired))
        )
    }
}
