package com.naylaaisyah.traveloapp.data.models

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * The refund preview maps backend tier percentages and amounts to what the
 * cancellation dialog shows the user before confirming.
 */
class RefundPreviewTest {

    @Test
    fun `full refund tier shows 100 percent and full amount`() {
        val preview = RefundPreview(
            bookingId = 7,
            totalPrice = 2_000_000.0,
            discountAmount = 0.0,
            percentage = 100,
            refundAmount = 2_000_000.0,
            tier = "full",
            tierLabel = "Full refund (7+ days before trip)",
            daysBefore = 7,
            eligible = true
        )

        assertEquals(100, preview.percentage)
        assertEquals(2_000_000.0, preview.refundAmount, 0.01)
        assertEquals("full", preview.tier)
        assertEquals(true, preview.eligible)
    }

    @Test
    fun `partial refund tier shows 50 percent and half amount`() {
        val preview = RefundPreview(
            bookingId = 7,
            totalPrice = 2_000_000.0,
            discountAmount = 500_000.0,
            percentage = 50,
            refundAmount = 750_000.0,
            tier = "partial",
            tierLabel = "Partial refund (3-6 days before trip)",
            daysBefore = 4,
            eligible = true
        )

        assertEquals(50, preview.percentage)
        assertEquals(750_000.0, preview.refundAmount, 0.01)
        assertEquals("partial", preview.tier)
    }

    @Test
    fun `no refund tier shows zero amount`() {
        val preview = RefundPreview(
            bookingId = 7,
            totalPrice = 2_000_000.0,
            discountAmount = 0.0,
            percentage = 0,
            refundAmount = 0.0,
            tier = "none",
            tierLabel = "No refund (less than 3 days before trip)",
            daysBefore = 1,
            eligible = true
        )

        assertEquals(0, preview.percentage)
        assertEquals(0.0, preview.refundAmount, 0.01)
        assertEquals("none", preview.tier)
    }

    @Test
    fun `not eligible preview has zero percentage and amount`() {
        val preview = RefundPreview(
            bookingId = 7,
            totalPrice = 2_000_000.0,
            discountAmount = 0.0,
            percentage = 0,
            refundAmount = 0.0,
            tier = "none",
            tierLabel = "Not eligible (ticket already used or expired)",
            daysBefore = 0,
            eligible = false,
            reason = "ticket_used"
        )

        assertEquals(false, preview.eligible)
        assertEquals("ticket_used", preview.reason)
        assertEquals(0, preview.percentage)
    }

    @Test
    fun `promo discount is reflected in total price only`() {
        // Refund is calculated from total_price (after promo), never subtotal.
        val preview = RefundPreview(
            bookingId = 7,
            totalPrice = 1_500_000.0,
            discountAmount = 500_000.0,
            promoCode = "WELCOME50",
            percentage = 100,
            refundAmount = 1_500_000.0,
            tier = "full",
            tierLabel = "Full refund (7+ days before trip)",
            daysBefore = 10,
            eligible = true
        )

        assertEquals("WELCOME50", preview.promoCode)
        assertEquals(1_500_000.0, preview.totalPrice, 0.01)
        assertEquals(500_000.0, preview.discountAmount, 0.01)
        assertEquals(1_500_000.0, preview.refundAmount, 0.01)
    }
}
