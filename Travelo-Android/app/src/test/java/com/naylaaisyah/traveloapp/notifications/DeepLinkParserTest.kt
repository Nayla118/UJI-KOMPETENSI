package com.naylaaisyah.traveloapp.notifications

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * The parser is the single mapping between FCM data and Navigation routes.
 * These tests pin every backend notification type, the screen override used by
 * newer payloads, and the "never navigate to 0" behaviour for incomplete data.
 */
class DeepLinkParserTest {

    private fun extras(vararg pairs: Pair<String, String>): Map<String, String> =
        mapOf(*pairs)

    @Test
    fun `payment paid opens the e ticket`() {
        assertEquals(
            DeepLinkTarget.ETicket(42),
            DeepLinkParser.parse(
                extras(
                    "type" to "payment_paid",
                    "screen" to "e_ticket",
                    "booking_id" to "42"
                )
            )
        )
    }

    @Test
    fun `payment failures and expiry open booking detail`() {
        listOf(
            "payment_failed",
            "payment_expired",
            "payment_after_expiry",
            "payment_update",
            "booking_expired"
        ).forEach { type ->
            assertEquals(
                type,
                DeepLinkTarget.BookingDetail(7),
                DeepLinkParser.parse(
                    extras("type" to type, "screen" to "booking_detail", "booking_id" to "7")
                )
            )
        }
    }

    @Test
    fun `ticket used opens the e ticket`() {
        assertEquals(
            DeepLinkTarget.ETicket(9),
            DeepLinkParser.parse(extras("type" to "ticket_used", "booking_id" to "9"))
        )
    }

    @Test
    fun `change request outcomes open booking detail`() {
        listOf(
            "change_request_approved",
            "change_request_rejected",
            "change_request_pending",
            "change_request_updated"
        ).forEach { type ->
            assertEquals(
                type,
                DeepLinkTarget.BookingDetail(11),
                DeepLinkParser.parse(
                    extras("type" to type, "screen" to "booking_detail", "booking_id" to "11")
                )
            )
        }
    }

    @Test
    fun `review moderation opens the review on its destination`() {
        assertEquals(
            DeepLinkTarget.Review(destinationId = 3, reviewId = 88),
            DeepLinkParser.parse(
                extras(
                    "type" to "review_moderated",
                    "screen" to "review",
                    "destination_id" to "3",
                    "review_id" to "88"
                )
            )
        )
    }

    @Test
    fun `trip reminder opens the e ticket`() {
        assertEquals(
            DeepLinkTarget.ETicket(15),
            DeepLinkParser.parse(
                extras("type" to "trip_reminder", "screen" to "e_ticket", "booking_id" to "15")
            )
        )
    }

    @Test
    fun `destination screen opens destination detail`() {
        assertEquals(
            DeepLinkTarget.Destination(21),
            DeepLinkParser.parse(
                extras(
                    "type" to "destination_promo",
                    "screen" to "destination",
                    "destination_id" to "21"
                )
            )
        )
    }

    @Test
    fun `a known screen wins over an unknown newer type`() {
        assertEquals(
            DeepLinkTarget.BookingDetail(5),
            DeepLinkParser.parse(
                extras(
                    "type" to "booking_updated_v2",
                    "screen" to "booking_detail",
                    "booking_id" to "5"
                )
            )
        )
    }

    @Test
    fun `a generic id is accepted when no named id is present`() {
        assertEquals(
            DeepLinkTarget.ETicket(19),
            DeepLinkParser.parse(extras("type" to "payment_paid", "id" to "19"))
        )
    }

    @Test
    fun `type and screen values are case insensitive`() {
        assertEquals(
            DeepLinkTarget.ETicket(2),
            DeepLinkParser.parse(
                extras(
                    "type" to " PAYMENT_PAID ",
                    "screen" to " E_TICKET ",
                    "booking_id" to "2"
                )
            )
        )
    }

    @Test
    fun `unknown type without a screen is ignored`() {
        assertNull(
            DeepLinkParser.parse(extras("type" to "something_new", "booking_id" to "4"))
        )
    }

    @Test
    fun `unknown screen without a known type is ignored`() {
        assertNull(
            DeepLinkParser.parse(
                extras("type" to "something_new", "screen" to "account", "booking_id" to "4")
            )
        )
    }

    @Test
    fun `incomplete booking targets are ignored`() {
        assertNull(DeepLinkParser.parse(extras("type" to "payment_paid")))
        assertNull(DeepLinkParser.parse(extras("screen" to "e_ticket")))
        assertNull(
            DeepLinkParser.parse(extras("type" to "payment_paid", "booking_id" to "not-a-number"))
        )
        assertNull(
            DeepLinkParser.parse(extras("type" to "payment_paid", "booking_id" to "0"))
        )
        assertNull(
            DeepLinkParser.parse(extras("type" to "payment_paid", "booking_id" to "-3"))
        )
    }

    @Test
    fun `incomplete review targets are ignored`() {
        assertNull(
            DeepLinkParser.parse(
                extras("type" to "review_moderated", "review_id" to "8")
            )
        )
        assertNull(
            DeepLinkParser.parse(
                extras("screen" to "review", "destination_id" to "0")
            )
        )
    }

    @Test
    fun `review id is optional when the destination is known`() {
        assertEquals(
            DeepLinkTarget.Review(destinationId = 6, reviewId = null),
            DeepLinkParser.parse(
                extras("type" to "review_moderated", "destination_id" to "6")
            )
        )
    }

    @Test
    fun `empty extras produce no navigation`() {
        assertNull(DeepLinkParser.parse(emptyMap()))
    }
}
