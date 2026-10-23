package com.naylaaisyah.traveloapp.ui.screens.booking

import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class SavedIdentityPrefillTest {

    private fun identity(
        identityType: String = "ktp",
        identityTypeOther: String? = null,
        canReuseDocument: Boolean = false,
        documentStatus: String? = "missing"
    ) = SavedIdentity(
        name = "Budi Santoso",
        identityType = identityType,
        identityTypeOther = identityTypeOther,
        identityNumber = "3171234567891234",
        dateOfBirth = "1990-01-01",
        canReuseDocument = canReuseDocument,
        documentStatus = documentStatus
    )

    @Test
    fun known_identity_types_are_mapped_for_prefill() {
        assertEquals(
            VisitorIdentityType.KIA,
            identity(identityType = "kia").toBookingPrefill().identityType
        )
        assertEquals(
            VisitorIdentityType.STUDENT_CARD,
            identity(identityType = "student_card").toBookingPrefill().identityType
        )
        assertEquals(
            VisitorIdentityType.OTHER,
            identity(identityType = "other", identityTypeOther = "Paspor")
                .toBookingPrefill()
                .identityType
        )
    }

    @Test
    fun unknown_identity_type_falls_back_to_ktp() {
        assertEquals(
            VisitorIdentityType.KTP,
            identity(identityType = "unknown").toBookingPrefill().identityType
        )
    }

    @Test
    fun saved_document_is_used_only_when_backend_marks_it_reusable() {
        val pending = identity(canReuseDocument = true, documentStatus = "pending")
            .toBookingPrefill()
        assertTrue(pending.useSavedDocument)
        assertEquals("pending", pending.documentStatus)

        val rejected = identity(canReuseDocument = false, documentStatus = "rejected")
            .toBookingPrefill()
        assertFalse(rejected.useSavedDocument)
        assertEquals("rejected", rejected.documentStatus)
    }
}
