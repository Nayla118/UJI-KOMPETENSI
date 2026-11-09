package com.naylaaisyah.traveloapp.data.availability

import com.naylaaisyah.traveloapp.data.models.AvailabilityDay
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Client-side availability is a convenience gate only: the booking endpoint
 * re-validates per-date capacity atomically. These tests pin the rules the UI
 * uses to decide whether to enable "Continue".
 */
class AvailabilityRulesTest {

    private fun day(
        remaining: Int,
        isClosed: Boolean = false
    ) = AvailabilityDay(
        date = "2026-11-10",
        capacity = 10,
        booked = 10 - remaining,
        remaining = remaining,
        isClosed = isClosed,
        available = !isClosed && remaining > 0
    )

    @Test
    fun `month is extracted from a full date`() {
        assertEquals("2026-11", AvailabilityRules.monthOf("2026-11-10"))
    }

    @Test
    fun `malformed dates have no month`() {
        assertNull(AvailabilityRules.monthOf(""))
        assertNull(AvailabilityRules.monthOf("2026"))
        assertNull(AvailabilityRules.monthOf("2026/11/10"))
    }

    @Test
    fun `unknown day does not block the booking form`() {
        assertTrue(AvailabilityRules.isBookable(null, people = 3))
        assertNull(AvailabilityRules.message(null, people = 3))
    }

    @Test
    fun `closed day is never bookable`() {
        val closed = day(remaining = 5, isClosed = true)

        assertFalse(AvailabilityRules.isBookable(closed, people = 1))
        assertEquals(
            "This date is closed for this tour package. Choose another date.",
            AvailabilityRules.message(closed, people = 1)
        )
    }

    @Test
    fun `full day is never bookable`() {
        val full = day(remaining = 0)

        assertFalse(AvailabilityRules.isBookable(full, people = 1))
        assertEquals(
            "This date is fully booked. Choose another date.",
            AvailabilityRules.message(full, people = 1)
        )
    }

    @Test
    fun `enough seats keeps the date bookable`() {
        val open = day(remaining = 4)

        assertTrue(AvailabilityRules.isBookable(open, people = 4))
        assertEquals("4 seats left on this date.", AvailabilityRules.message(open, people = 4))
    }

    @Test
    fun `more travelers than seats blocks the date`() {
        val tight = day(remaining = 2)

        assertFalse(AvailabilityRules.isBookable(tight, people = 3))
        assertEquals(
            "Only 2 seats left on this date. Reduce travelers or choose another date.",
            AvailabilityRules.message(tight, people = 3)
        )
    }

    @Test
    fun `single remaining seat uses singular copy`() {
        val last = day(remaining = 1)

        assertTrue(AvailabilityRules.isBookable(last, people = 1))
        assertEquals("1 seat left on this date.", AvailabilityRules.message(last, people = 1))

        assertEquals(
            "Only 1 seat left on this date. Reduce travelers or choose another date.",
            AvailabilityRules.message(last, people = 2)
        )
    }

    @Test
    fun `people count is clamped to at least one`() {
        val last = day(remaining = 1)

        assertTrue(AvailabilityRules.isBookable(last, people = 0))
        assertTrue(AvailabilityRules.isBookable(last, people = -5))
    }
}
