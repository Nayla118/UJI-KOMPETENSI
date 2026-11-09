package com.naylaaisyah.traveloapp.data.models

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The UI only offers cancellation when the booking can still be changed. The
 * backend re-checks all of these conditions (plus the visit date and
 * ownership) before accepting a request.
 */
class BookingChangeEligibilityTest {

    private fun booking(
        status: String = "confirmed",
        paymentStatus: String? = "paid",
        ticketUsageStatus: String = "unused",
        latestChangeRequest: BookingChangeRequest? = null
    ) = Booking(
        id = 1,
        userId = 7,
        tourPackageId = 3,
        peopleCount = 2,
        totalPrice = 2_000_000.0,
        status = status,
        paymentStatus = paymentStatus,
        ticketUsageStatus = ticketUsageStatus,
        bookingDate = "2026-11-10",
        latestChangeRequest = latestChangeRequest
    )

    private fun pendingRequest() = BookingChangeRequest(
        id = 10,
        bookingId = 1,
        type = BookingChangeRequest.TYPE_CANCELLATION,
        status = BookingChangeRequest.STATUS_PENDING
    )

    @Test
    fun `paid confirmed unused booking can request a cancellation`() {
        assertTrue(booking().canRequestCancellation())
    }

    @Test
    fun `unpaid booking cannot request a cancellation`() {
        assertFalse(booking(paymentStatus = "pending").canRequestCancellation())
        assertFalse(booking(paymentStatus = null).canRequestCancellation())
    }

    @Test
    fun `booking must still be confirmed`() {
        assertFalse(booking(status = "cancelled").canRequestCancellation())
        assertFalse(booking(status = "completed").canRequestCancellation())
    }

    @Test
    fun `used or expired tickets cannot request a cancellation`() {
        assertFalse(booking(ticketUsageStatus = "used").canRequestCancellation())
        assertFalse(booking(ticketUsageStatus = "expired").canRequestCancellation())
    }

    @Test
    fun `a pending cancellation request hides the action`() {
        assertFalse(booking(latestChangeRequest = pendingRequest()).canRequestCancellation())
    }

    @Test
    fun `a reviewed request does not hide the action`() {
        val rejected = pendingRequest().copy(status = BookingChangeRequest.STATUS_REJECTED)
        val approved = pendingRequest().copy(status = BookingChangeRequest.STATUS_APPROVED)

        assertTrue(booking(latestChangeRequest = rejected).canRequestCancellation())
        assertTrue(booking(latestChangeRequest = approved).canRequestCancellation())
    }

    @Test
    fun `cancellation request helpers identify the type and pending state`() {
        val cancellation = pendingRequest()

        assertTrue(cancellation.isCancellation)
        assertTrue(cancellation.isPending)
    }
}
