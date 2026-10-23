package com.naylaaisyah.traveloapp.ui.screens.profile

import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.TourPackage
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ProfileInsightsTest {

    private fun booking(
        id: Int,
        date: String,
        status: String = "confirmed",
        paymentStatus: String? = "settlement"
    ) = Booking(
        id = id,
        userId = 1,
        tourPackageId = 1,
        peopleCount = 2,
        totalPrice = 1_500_000.0,
        status = status,
        paymentStatus = paymentStatus,
        bookingDate = date,
        tourPackage = TourPackage(
            id = 1,
            title = "Bali Escape",
            price = 1_500_000,
            durationDays = 3,
            destinationId = 1,
            maxPeople = 10
        )
    )

    @Test
    fun empty_profile_reports_every_missing_field() {
        val completion = calculateProfileCompletion(
            name = null,
            email = null,
            phone = null,
            dateOfBirth = null,
            city = null,
            hasSavedIdentity = false
        )

        assertEquals(0, completion.percent)
        assertEquals(
            listOf("name", "email", "phone number", "date of birth", "home city", "saved identity"),
            completion.missingLabels
        )
        assertEquals(
            listOf("name", "email", "phone number", "date of birth", "home city"),
            completion.missingPersonalLabels
        )
        assertTrue(completion.missingSavedIdentity)
    }

    @Test
    fun complete_profile_reaches_one_hundred_percent() {
        val completion = calculateProfileCompletion(
            name = "Budi Santoso",
            email = "budi@example.com",
            phone = "+62 812 3456 7890",
            dateOfBirth = "1995-04-12",
            city = "Bandung",
            hasSavedIdentity = true
        )

        assertEquals(100, completion.percent)
        assertTrue(completion.missingLabels.isEmpty())
        assertTrue(completion.missingPersonalLabels.isEmpty())
        assertEquals(false, completion.missingSavedIdentity)
    }

    @Test
    fun missing_saved_identity_does_not_ask_for_personal_details() {
        val completion = calculateProfileCompletion(
            name = "Budi Santoso",
            email = "budi@example.com",
            phone = "+62 812 3456 7890",
            dateOfBirth = "1995-04-12",
            city = "Bandung",
            hasSavedIdentity = false
        )

        assertEquals(83, completion.percent)
        assertEquals(listOf("saved identity"), completion.missingLabels)
        assertTrue(completion.missingPersonalLabels.isEmpty())
        assertTrue(completion.missingSavedIdentity)
    }

    @Test
    fun missing_labels_are_joined_readably() {
        assertEquals("", joinProfileLabels(emptyList()))
        assertEquals("phone number", joinProfileLabels(listOf("phone number")))
        assertEquals("phone number and home city", joinProfileLabels(listOf("phone number", "home city")))
        assertEquals(
            "phone number, home city, and saved identity",
            joinProfileLabels(listOf("phone number", "home city", "saved identity"))
        )
    }

    @Test
    fun next_trip_is_the_earliest_upcoming_non_cancelled_booking() {
        val next = nextUpcomingBooking(
            bookings = listOf(
                booking(id = 1, date = "2026-10-20"),
                booking(id = 2, date = "2026-10-23"),
                booking(id = 3, date = "2026-10-22", status = "cancelled"),
                booking(id = 4, date = "2026-10-25")
            ),
            todayIso = "2026-10-22"
        )

        assertEquals(2, next?.id)
    }

    @Test
    fun next_trip_is_absent_when_only_past_or_cancelled_bookings_exist() {
        val next = nextUpcomingBooking(
            bookings = listOf(
                booking(id = 1, date = "2026-10-20"),
                booking(id = 2, date = "2026-10-23", status = "cancelled")
            ),
            todayIso = "2026-10-22"
        )

        assertNull(next)
    }

    @Test
    fun paid_booking_uses_a_success_status() {
        val status = profileTripStatus(booking(id = 1, date = "2026-10-23"))

        assertEquals("Paid", status.label)
        assertEquals(ProfileTripTone.Success, status.tone)
    }

    @Test
    fun pending_payment_uses_a_warning_status() {
        val status = profileTripStatus(
            booking(id = 1, date = "2026-10-23", paymentStatus = "pending")
        )

        assertEquals("Payment pending", status.label)
        assertEquals(ProfileTripTone.Warning, status.tone)
    }

    @Test
    fun cancelled_booking_stays_cancelled_even_if_payment_settled() {
        val status = profileTripStatus(
            booking(id = 1, date = "2026-10-23", status = "cancelled")
        )

        assertEquals("Cancelled", status.label)
        assertEquals(ProfileTripTone.Danger, status.tone)
    }

    @Test
    fun confirmed_booking_without_payment_metadata_is_readable() {
        val status = profileTripStatus(
            booking(id = 1, date = "2026-10-23", paymentStatus = null)
        )

        assertEquals("Confirmed", status.label)
        assertEquals(ProfileTripTone.Success, status.tone)
    }
}
