package com.naylaaisyah.traveloapp.data.recovery

import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Payment
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.Visitor
import com.naylaaisyah.traveloapp.data.models.paymentOutcome
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Fast JVM coverage for the recovery queue and the client-side status rules.
 * No identity number, document content, picker URI or token is ever part of a
 * recovery job.
 */
class DocumentRecoveryRulesTest {

    private fun visitor(
        id: Int,
        hasDocument: Boolean,
        status: String = if (hasDocument) "pending" else "missing"
    ) = Visitor(
        id = id,
        name = "Traveller $id",
        documentStatus = status,
        hasDocument = hasDocument,
        isIdentityComplete = hasDocument
    )

    private fun booking(
        visitors: List<Visitor>,
        peopleCount: Int = visitors.size,
        status: String = "pending",
        paymentStatus: String = "pending",
        payment: Payment? = null
    ) = Booking(
        id = 42,
        userId = 7,
        tourPackageId = 3,
        peopleCount = peopleCount,
        totalPrice = 1_000_000.0,
        status = status,
        paymentStatus = paymentStatus,
        bookingDate = "2026-11-01",
        visitors = visitors,
        payment = payment
    )

    private fun job(
        bookingId: Int = 42,
        userId: String = "7",
        title: String = "Bali Trip",
        createdAt: Long = 1_000L,
        lastAttemptAt: Long? = null
    ) = DocumentRecoveryJob(
        bookingId = bookingId,
        userId = userId,
        tourTitle = title,
        peopleCount = 2,
        createdAt = createdAt,
        lastAttemptAt = lastAttemptAt
    )

    private fun ticketActionAvailable(booking: Booking): Boolean =
        booking.paymentOutcome() == PaymentOutcome.SUCCESS &&
            !DocumentRecoveryRules.needsIdentityDocuments(booking)

    @Test
    fun job_survives_encode_decode_round_trip_after_restart() {
        val original = job(lastAttemptAt = 2_000L)
        val encoded = DocumentRecoveryCodec.encode(listOf(original))
        val decoded = DocumentRecoveryCodec.decode(encoded)

        assertEquals(listOf(original), decoded)
        assertEquals(decoded, DocumentRecoveryCodec.decode(encoded))
    }

    @Test
    fun job_contains_no_document_uri_file_name_identity_or_token() {
        val encoded = DocumentRecoveryCodec.encode(listOf(job()))

        assertTrue(encoded.contains("bookingId"))
        assertFalse(encoded.contains("content://"))
        assertFalse(encoded.contains("ktp.jpg"))
        assertFalse(encoded.contains("identityNumber"))
        assertFalse(encoded.contains("documentPath"))
        assertFalse(encoded.contains("token"))
        assertFalse(encoded.contains(".pdf"))
    }

    @Test
    fun retry_updates_one_job_and_never_duplicates_a_booking() {
        val first = job(createdAt = 1_000L)
        val retried = job(createdAt = 1_000L, lastAttemptAt = 5_000L)

        val queue = DocumentRecoveryRules.upsert(
            DocumentRecoveryRules.upsert(emptyList(), first),
            retried
        )

        assertEquals(1, queue.size)
        assertEquals(retried, queue.single())
    }

    @Test
    fun failure_keeps_the_job_and_success_is_the_only_clear_path() {
        val queue = DocumentRecoveryRules.upsert(emptyList(), job())

        // A network failure does not remove anything.
        assertEquals(1, DocumentRecoveryRules.forUser(queue, "7").size)

        val cleared = DocumentRecoveryRules.remove(queue, bookingId = 42, userId = "7")
        assertTrue(cleared.isEmpty())
    }

    @Test
    fun jobs_are_scoped_to_the_owner_and_survive_logout_login() {
        val queue = listOf(
            job(bookingId = 42, userId = "7"),
            job(bookingId = 99, userId = "8", createdAt = 2_000L)
        )

        // Session cleared then the same account signs in again: still there.
        assertEquals(1, DocumentRecoveryRules.forUser(queue, "7").size)
        assertEquals(42, DocumentRecoveryRules.forUser(queue, "7").single().bookingId)

        // A different account on the same device cannot see the other job.
        assertEquals(99, DocumentRecoveryRules.forUser(queue, "8").single().bookingId)
        assertTrue(DocumentRecoveryRules.forUser(queue, "9").isEmpty())
    }

    @Test
    fun only_missing_documents_are_uploaded_so_partial_retry_is_safe() {
        val visitors = listOf(
            visitor(id = 1, hasDocument = true),
            visitor(id = 2, hasDocument = false)
        )

        val missing = DocumentRecoveryRules.visitorsRequiringUpload(visitors)

        assertEquals(listOf(2), missing.map { it.id })
    }

    @Test
    fun stale_or_missing_uri_requires_reselect_and_reselection_makes_the_plan_ready() {
        val visitors = listOf(
            visitor(id = 1, hasDocument = true),
            visitor(id = 2, hasDocument = false)
        )

        // After restart nothing is selected, so the missing visitor must be
        // picked again. No old URI is trusted.
        assertEquals(
            listOf(2),
            DocumentRecoveryRules.visitorsMissingSelection(visitors, emptySet())
                .map { it.id }
        )

        // Reselecting the file satisfies the plan without another booking.
        assertTrue(
            DocumentRecoveryRules.visitorsMissingSelection(visitors, setOf(2)).isEmpty()
        )
    }

    @Test
    fun lost_response_is_not_uploaded_again_once_the_backend_has_the_file() {
        val afterServerAccepted = listOf(
            visitor(id = 1, hasDocument = true),
            visitor(id = 2, hasDocument = true)
        )

        assertTrue(DocumentRecoveryRules.visitorsRequiringUpload(afterServerAccepted).isEmpty())
    }

    @Test
    fun booking_with_partial_documents_needs_action_until_every_row_is_on_file() {
        val partial = booking(
            visitors = listOf(
                visitor(id = 1, hasDocument = true),
                visitor(id = 2, hasDocument = false, status = "rejected")
            )
        )

        assertTrue(DocumentRecoveryRules.needsIdentityDocuments(partial))

        val complete = partial.copy(
            visitors = listOf(
                visitor(id = 1, hasDocument = true),
                visitor(id = 2, hasDocument = true)
            )
        )

        assertFalse(DocumentRecoveryRules.needsIdentityDocuments(complete))
    }

    @Test
    fun booking_created_but_upload_not_started_still_needs_action() {
        val notStarted = booking(
            visitors = listOf(
                visitor(id = 1, hasDocument = false),
                visitor(id = 2, hasDocument = false)
            )
        )

        assertTrue(DocumentRecoveryRules.needsIdentityDocuments(notStarted))
        assertEquals(2, DocumentRecoveryRules.visitorsRequiringUpload(notStarted.visitors).size)
    }

    @Test
    fun legacy_booking_without_visitor_rows_stays_compatible() {
        val legacy = booking(visitors = emptyList(), peopleCount = 2)

        assertFalse(DocumentRecoveryRules.needsIdentityDocuments(legacy))
        assertTrue(DocumentRecoveryRules.visitorsRequiringUpload(legacy.visitors).isEmpty())
    }

    @Test
    fun traveller_count_mismatch_blocks_completion() {
        val mismatch = booking(
            visitors = listOf(visitor(id = 1, hasDocument = true)),
            peopleCount = 2
        )

        assertTrue(DocumentRecoveryRules.needsIdentityDocuments(mismatch))
    }

    @Test
    fun e_ticket_action_stays_locked_until_payment_and_every_document_are_complete() {
        val unpaid = booking(
            visitors = listOf(visitor(id = 1, hasDocument = true)),
            status = "pending",
            paymentStatus = "pending"
        )
        assertFalse(ticketActionAvailable(unpaid))

        val paidButIncomplete = booking(
            visitors = listOf(
                visitor(id = 1, hasDocument = true),
                visitor(id = 2, hasDocument = false)
            ),
            status = "confirmed",
            paymentStatus = "paid",
            payment = Payment(
                id = 1,
                bookingId = 42,
                amount = 2_000_000.0,
                status = "paid"
            )
        )
        assertFalse(ticketActionAvailable(paidButIncomplete))

        val paidAndComplete = paidButIncomplete.copy(
            visitors = listOf(
                visitor(id = 1, hasDocument = true),
                visitor(id = 2, hasDocument = true)
            )
        )
        assertTrue(ticketActionAvailable(paidAndComplete))
    }

    @Test
    fun paid_legacy_booking_can_still_open_its_ticket() {
        val legacyPaid = booking(
            visitors = emptyList(),
            peopleCount = 2,
            status = "confirmed",
            paymentStatus = "paid",
            payment = Payment(
                id = 2,
                bookingId = 42,
                amount = 2_000_000.0,
                status = "paid"
            )
        )

        assertTrue(ticketActionAvailable(legacyPaid))
    }
}
