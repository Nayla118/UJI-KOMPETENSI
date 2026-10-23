package com.naylaaisyah.traveloapp.data.models

import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Guards the API contract used by the verified-visit review UI. Laravel
 * snake-cases loaded relations, so a booking's package must come from
 * `tour_package` (not `tourPackage`), and review aggregates must parse into
 * the public models exactly as the backend sends them.
 */
class ReviewModelsTest {

    private val gson = Gson()

    @Test
    fun bookingParsesNestedPackageAndOwnerReviewState() {
        val json = """
            {
              "id": 10,
              "user_id": 3,
              "tour_package_id": 7,
              "people_count": 2,
              "total_price": 500000,
              "booking_status": "confirmed",
              "payment_status": "paid",
              "ticket_usage_status": "used",
              "ticket_used_at": "2026-10-20T09:00:00+07:00",
              "booking_date": "2026-10-20",
              "tour_package": {
                "id": 7,
                "title": "Bali Adventure",
                "price": 250000,
                "duration_days": 3,
                "destination_id": 4,
                "max_people": 10,
                "rating": 4.5,
                "review_count": 2,
                "destination": {
                  "id": 4,
                  "name": "Bali",
                  "country": "Indonesia",
                  "city": "Denpasar",
                  "rating": 4.5,
                  "review_count": 2
                }
              },
              "review": {
                "id": 12,
                "booking_id": 10,
                "rating": 5,
                "comment": "Wonderful visit",
                "status": "published",
                "verified_visit": true,
                "created_at": "2026-10-20T10:00:00+07:00"
              },
              "review_status": "reviewed",
              "can_review": false
            }
        """.trimIndent()

        val booking = gson.fromJson(json, Booking::class.java)

        assertEquals("reviewed", booking.reviewStatus)
        assertFalse(booking.canReview ?: true)
        assertNotNull(booking.tourPackage)
        assertEquals("Bali Adventure", booking.tourPackage?.title)
        assertEquals(2, booking.tourPackage?.reviewCount)
        assertEquals(4.5, booking.tourPackage?.rating ?: 0.0, 0.0)
        assertEquals(5, booking.review?.rating)
        assertTrue(booking.review?.verifiedVisit == true)
        assertTrue(booking.review?.isPublished == true)
    }

    @Test
    fun publicReviewListParsesSummaryDistributionAndMeta() {
        val json = """
            {
              "summary": {
                "average": 4.5,
                "count": 2,
                "distribution": {"1": 0, "2": 0, "3": 0, "4": 1, "5": 1}
              },
              "reviews": [
                {
                  "id": 12,
                  "rating": 5,
                  "comment": "Wonderful visit",
                  "reviewer_name": "Nayla A.",
                  "verified_visit": true,
                  "created_at": "2026-10-20T10:00:00+07:00"
                }
              ],
              "meta": {"current_page": 1, "last_page": 3, "per_page": 5, "total": 11}
            }
        """.trimIndent()

        val data = gson.fromJson(json, ReviewListData::class.java)

        assertEquals(4.5, data.summary.average ?: 0.0, 0.0)
        assertEquals(2, data.summary.count)
        assertEquals(1, data.summary.distribution["4"])
        assertEquals(1, data.summary.distribution["5"])
        assertEquals("Nayla A.", data.reviews.single().reviewerName)
        assertTrue(data.reviews.single().verifiedVisit)
        assertEquals(3, data.meta.lastPage)
        assertEquals(11, data.meta.total)
    }

    @Test
    fun hiddenOwnerReviewIsNotTreatedAsPublished() {
        val review = gson.fromJson(
            """
                {
                  "id": 12,
                  "rating": 4,
                  "status": "hidden",
                  "booking_id": 10,
                  "moderation_reason": "Checking details"
                }
            """.trimIndent(),
            Review::class.java
        )

        assertFalse(review.isPublished)
        assertEquals("Checking details", review.moderationReason)
    }
}
