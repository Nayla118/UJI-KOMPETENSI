package com.naylaaisyah.traveloapp.data.payments

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The payment countdown must be anchored to backend time, never the device
 * wall clock. These tests pin the remaining-time maths, the mm:ss formatting
 * and the "Kedaluwarsa" boundary so a wrong phone clock cannot keep a released
 * booking payable.
 */
class PaymentCountdownTest {

    // ---------- Remaining seconds ----------

    @Test
    fun `server remaining seconds are used verbatim at zero elapsed`() {
        assertEquals(3600L, PaymentCountdown.remainingSeconds(3600, 0L))
    }

    @Test
    fun `monotonic elapsed time is subtracted`() {
        assertEquals(3597L, PaymentCountdown.remainingSeconds(3600, 3_000L))
    }

    @Test
    fun `sub-second elapsed time is ignored`() {
        assertEquals(3600L, PaymentCountdown.remainingSeconds(3600, 999L))
    }

    @Test
    fun `remaining time never goes negative`() {
        assertEquals(0L, PaymentCountdown.remainingSeconds(10, 60_000L))
    }

    @Test
    fun `negative elapsed time cannot add time`() {
        assertEquals(10L, PaymentCountdown.remainingSeconds(10, -5_000L))
    }

    @Test
    fun `missing server value means zero`() {
        assertEquals(0L, PaymentCountdown.remainingSeconds(null, 1_000L))
    }

    // ---------- Deadline - server_time fallback ----------

    @Test
    fun `fallback derives remaining seconds from server timestamps`() {
        assertEquals(
            3600L,
            PaymentCountdown.remainingSecondsBetween(
                "2026-10-25T04:00:00Z",
                "2026-10-25T03:00:00Z"
            )
        )
    }

    @Test
    fun `fallback accepts fractional seconds and offsets`() {
        assertEquals(
            90L,
            PaymentCountdown.remainingSecondsBetween(
                "2026-10-25T11:01:30.000000Z",
                "2026-10-25T11:00:00Z"
            )
        )

        assertEquals(
            90L,
            PaymentCountdown.remainingSecondsBetween(
                "2026-10-25T18:01:30+07:00",
                "2026-10-25T18:00:00+07:00"
            )
        )
    }

    @Test
    fun `fallback clamps a passed deadline to zero`() {
        assertEquals(
            0L,
            PaymentCountdown.remainingSecondsBetween(
                "2026-10-25T03:00:00Z",
                "2026-10-25T03:10:00Z"
            )
        )
    }

    @Test
    fun `fallback returns null for missing or invalid timestamps`() {
        assertNull(PaymentCountdown.remainingSecondsBetween(null, "2026-10-25T03:00:00Z"))
        assertNull(PaymentCountdown.remainingSecondsBetween("2026-10-25T04:00:00Z", null))
        assertNull(PaymentCountdown.remainingSecondsBetween("not-a-date", "2026-10-25T03:00:00Z"))
    }

    @Test
    fun `resolve prefers the server precomputed seconds`() {
        assertEquals(
            120L,
            PaymentCountdown.resolveRemaining(
                serverRemainingSeconds = 120,
                deadlineIso = "2026-10-25T04:00:00Z",
                serverTimeIso = "2026-10-25T03:00:00Z"
            )
        )
    }

    @Test
    fun `resolve falls back to deadline and server time`() {
        assertEquals(
            599L,
            PaymentCountdown.resolveRemaining(
                serverRemainingSeconds = null,
                deadlineIso = "2026-10-25T04:00:00Z",
                serverTimeIso = "2026-10-25T03:50:00Z",
                elapsedMillis = 1_000L
            )
        )
    }

    @Test
    fun `resolve returns null when no window exists`() {
        assertNull(PaymentCountdown.resolveRemaining(null, null, null))
    }

    // ---------- Expired boundary ----------

    @Test
    fun `server expired flag wins even with seconds left`() {
        assertTrue(PaymentCountdown.isExpired(true, 100L))
    }

    @Test
    fun `reaching zero means expired`() {
        assertTrue(PaymentCountdown.isExpired(false, 0L))
    }

    @Test
    fun `time left means not expired`() {
        assertFalse(PaymentCountdown.isExpired(false, 1L))
    }

    @Test
    fun `unknown window is not reported as expired`() {
        assertFalse(PaymentCountdown.isExpired(null, null))
    }

    // ---------- Formatting ----------

    @Test
    fun `format renders mm ss with two digits`() {
        assertEquals("00:00", PaymentCountdown.format(0L))
        assertEquals("00:09", PaymentCountdown.format(9L))
        assertEquals("00:59", PaymentCountdown.format(59L))
        assertEquals("01:00", PaymentCountdown.format(60L))
        assertEquals("59:59", PaymentCountdown.format(3599L))
        assertEquals("60:00", PaymentCountdown.format(3600L))
        assertEquals("122:05", PaymentCountdown.format(7325L))
    }

    @Test
    fun `format clamps negative seconds to zero`() {
        assertEquals("00:00", PaymentCountdown.format(-10L))
    }

    @Test
    fun `format or expired uses the required label`() {
        assertEquals("Kedaluwarsa", PaymentCountdown.formatOrExpired(0L))
        assertEquals("Kedaluwarsa", PaymentCountdown.formatOrExpired(null))
        assertEquals("00:01", PaymentCountdown.formatOrExpired(1L))
        assertEquals("01:30", PaymentCountdown.formatOrExpired(90L))
    }

    // ---------- Parsing ----------

    @Test
    fun `parser accepts zulu and offset timestamps`() {
        assertEquals(
            PaymentCountdown.parseInstant("2026-10-25T11:00:00+07:00"),
            PaymentCountdown.parseInstant("2026-10-25T04:00:00Z")
        )
        assertNull(PaymentCountdown.parseInstant(null))
        assertNull(PaymentCountdown.parseInstant(""))
    }
}
