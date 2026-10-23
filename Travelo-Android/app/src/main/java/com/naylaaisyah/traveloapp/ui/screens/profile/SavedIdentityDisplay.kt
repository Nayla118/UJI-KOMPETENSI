package com.naylaaisyah.traveloapp.ui.screens.profile

import com.naylaaisyah.traveloapp.data.models.SavedIdentity

/**
 * Display-only helpers for the account's saved booker identity. The raw
 * identity number is never shown in the profile; only the last four
 * characters are exposed, matching the masking used elsewhere in the app.
 */
internal fun maskSavedIdentityNumber(number: String?): String {
    val value = number?.trim().orEmpty()
    if (value.isEmpty()) return "Not provided"

    return "\u2022\u2022\u2022\u2022 ${value.takeLast(4)}"
}

internal fun savedIdentityTypeLabel(identity: SavedIdentity): String {
    return when (identity.identityType.lowercase()) {
        "ktp" -> "KTP"
        "kia" -> "KIA"
        "student_card" -> "Student card"
        "other" -> identity.identityTypeOther
            ?.takeIf { it.isNotBlank() }
            ?: "Other identity"
        else -> identity.identityType
            .replace('_', ' ')
            .replaceFirstChar { it.uppercase() }
    }
}

internal fun savedIdentityDocumentLabel(identity: SavedIdentity): String {
    if (!identity.hasDocument) return "No saved document"

    return when (identity.documentStatus?.lowercase()) {
        "pending" -> "Document waiting for review"
        "verified" -> "Document verified"
        "rejected" -> "Document needs replacing"
        "missing" -> "No saved document"
        else -> "Document saved"
    }
}
