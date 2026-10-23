package com.naylaaisyah.traveloapp.ui.screens.profile

import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import org.junit.Assert.assertEquals
import org.junit.Test

class SavedIdentityDisplayTest {

    private fun identity(
        identityType: String = "ktp",
        identityTypeOther: String? = null,
        hasDocument: Boolean = false,
        documentStatus: String? = null
    ) = SavedIdentity(
        name = "Budi Santoso",
        identityType = identityType,
        identityTypeOther = identityTypeOther,
        identityNumber = "3171234567891234",
        hasDocument = hasDocument,
        documentStatus = documentStatus
    )

    @Test
    fun identity_number_is_masked_to_the_last_four_characters() {
        assertEquals("\u2022\u2022\u2022\u2022 1234", maskSavedIdentityNumber("3171234567891234"))
        assertEquals("\u2022\u2022\u2022\u2022 1234", maskSavedIdentityNumber(" 1234 "))
        assertEquals("Not provided", maskSavedIdentityNumber(null))
        assertEquals("Not provided", maskSavedIdentityNumber("   "))
    }

    @Test
    fun identity_type_labels_are_readable() {
        assertEquals("KTP", savedIdentityTypeLabel(identity(identityType = "ktp")))
        assertEquals("KIA", savedIdentityTypeLabel(identity(identityType = "kia")))
        assertEquals(
            "Student card",
            savedIdentityTypeLabel(identity(identityType = "student_card"))
        )
        assertEquals(
            "Paspor",
            savedIdentityTypeLabel(
                identity(identityType = "other", identityTypeOther = "Paspor")
            )
        )
    }

    @Test
    fun document_status_labels_do_not_leak_the_file_path() {
        assertEquals(
            "No saved document",
            savedIdentityDocumentLabel(identity(hasDocument = false))
        )
        assertEquals(
            "Document waiting for review",
            savedIdentityDocumentLabel(
                identity(hasDocument = true, documentStatus = "pending")
            )
        )
        assertEquals(
            "Document verified",
            savedIdentityDocumentLabel(
                identity(hasDocument = true, documentStatus = "verified")
            )
        )
        assertEquals(
            "Document needs replacing",
            savedIdentityDocumentLabel(
                identity(hasDocument = true, documentStatus = "rejected")
            )
        )
    }
}
