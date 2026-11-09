package com.naylaaisyah.traveloapp.data.promo

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * The backend owns promo validation. These tests pin the client's translation
 * of its stable `reason` codes so every rejection gets actionable copy.
 */
class PromoErrorMessagesTest {

    @Test
    fun `every documented reason maps to its own message`() {
        val reasons = listOf(
            PromoErrorMessages.REASON_NOT_FOUND,
            PromoErrorMessages.REASON_INACTIVE,
            PromoErrorMessages.REASON_NOT_STARTED,
            PromoErrorMessages.REASON_EXPIRED,
            PromoErrorMessages.REASON_QUOTA_EXHAUSTED,
            PromoErrorMessages.REASON_PER_USER_LIMIT,
            PromoErrorMessages.REASON_MIN_TRANSACTION,
            PromoErrorMessages.REASON_NOT_APPLICABLE
        )

        val messages = reasons.map(PromoErrorMessages::messageForReason)

        assertEquals(reasons.size, messages.toSet().size)
        messages.forEach { message ->
            assertEquals(false, message == PromoErrorMessages.FALLBACK)
        }
    }

    @Test
    fun `expired code explains why it cannot be used`() {
        assertEquals(
            "This promo code has expired.",
            PromoErrorMessages.messageForReason(PromoErrorMessages.REASON_EXPIRED)
        )
    }

    @Test
    fun `per user limit explains the user already used it`() {
        assertEquals(
            "You've already used this promo code.",
            PromoErrorMessages.messageForReason(PromoErrorMessages.REASON_PER_USER_LIMIT)
        )
    }

    @Test
    fun `unknown reason falls back to the generic copy`() {
        assertEquals(
            PromoErrorMessages.FALLBACK,
            PromoErrorMessages.messageForReason("something_new")
        )
    }

    @Test
    fun `null reason falls back to the generic copy`() {
        assertEquals(
            PromoErrorMessages.FALLBACK,
            PromoErrorMessages.messageForReason(null)
        )
    }

    @Test
    fun `reason is extracted from an error body`() {
        val body = """{"success":false,"message":"This promo code has expired.","reason":"expired"}"""

        assertEquals(
            PromoErrorMessages.REASON_EXPIRED,
            PromoErrorMessages.reasonFromBody(body)
        )
    }

    @Test
    fun `reason wins over the server message`() {
        val body = """{"success":false,"message":"raw server text","reason":"quota_exhausted"}"""

        assertEquals(
            PromoErrorMessages.messageForReason(PromoErrorMessages.REASON_QUOTA_EXHAUSTED),
            PromoErrorMessages.messageFromBody(body)
        )
    }

    @Test
    fun `validation error without a reason keeps the server message`() {
        val body = """{"success":false,"message":"Validation error","errors":{}}"""

        assertEquals("Validation error", PromoErrorMessages.messageFromBody(body))
    }

    @Test
    fun `missing body uses the caller fallback`() {
        assertEquals(
            "Couldn't apply this promo code.",
            PromoErrorMessages.messageFromBody(null, "Couldn't apply this promo code.")
        )
        assertNull(PromoErrorMessages.reasonFromBody(null))
    }
}
