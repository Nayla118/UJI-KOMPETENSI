package com.naylaaisyah.traveloapp.ui.screens.booking

import com.naylaaisyah.traveloapp.data.models.SavedIdentity

/**
 * Pure mapping from the account-level saved identity to the booker form.
 * Kept outside the composable so the prefill rules are unit-testable.
 */
data class SavedIdentityPrefill(
    val name: String,
    val identityType: VisitorIdentityType,
    val identityTypeOther: String,
    val identityNumber: String,
    val dateOfBirth: String?,
    val useSavedDocument: Boolean,
    val documentStatus: String?
)

fun SavedIdentity.toBookingPrefill(): SavedIdentityPrefill = SavedIdentityPrefill(
    name = name,
    identityType = VisitorIdentityType.fromValue(identityType),
    identityTypeOther = identityTypeOther.orEmpty(),
    identityNumber = identityNumber,
    dateOfBirth = dateOfBirth,
    // The server decides whether the saved copy is still usable. A rejected
    // document must not be reused automatically.
    useSavedDocument = canReuseDocument,
    documentStatus = documentStatus
)
