package com.naylaaisyah.traveloapp.ui.screens.payment

import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.PaymentStatusData
import com.naylaaisyah.traveloapp.data.models.PaymentStatusInfo
import com.naylaaisyah.traveloapp.data.models.outcome
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * The WebView auto-return is allowed only when the backend reports success.
 * These tests pin the mapping used by that decision so a UI-only "success"
 * page can never close the payment flow on its own.
 */
class PaymentOutcomeTest {

    @Test
    fun paid_backend_status_is_success() {
        val data = PaymentStatusData(
            bookingStatus = "confirmed",
            paymentStatus = "paid",
            status = "paid"
        )

        assertEquals(PaymentOutcome.SUCCESS, data.outcome())
    }

    @Test
    fun confirmed_booking_is_success_even_before_payment_status_refreshes() {
        val data = PaymentStatusData(
            bookingStatus = "confirmed",
            paymentStatus = "pending"
        )

        assertEquals(PaymentOutcome.SUCCESS, data.outcome())
    }

    @Test
    fun pending_backend_status_keeps_waiting() {
        val data = PaymentStatusData(
            bookingStatus = "pending",
            paymentStatus = "pending",
            status = "pending"
        )

        assertEquals(PaymentOutcome.WAITING, data.outcome())
    }

    @Test
    fun expired_backend_status_is_not_success() {
        val data = PaymentStatusData(
            bookingStatus = "cancelled",
            paymentStatus = "expired"
        )

        assertEquals(PaymentOutcome.EXPIRED, data.outcome())
    }

    @Test
    fun cancelled_transaction_is_reported_as_cancelled() {
        val data = PaymentStatusData(
            bookingStatus = "cancelled",
            paymentStatus = "failed",
            payment = PaymentStatusInfo(
                status = "failed",
                transactionStatus = "cancel"
            )
        )

        assertEquals(PaymentOutcome.CANCELLED, data.outcome())
    }
}
