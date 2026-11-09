package com.naylaaisyah.traveloapp.data.promo

/**
 * Maps the backend's stable promo rejection reasons to copy a traveller can
 * act on.
 *
 * The server is the only source of truth for validity and price. This object
 * only translates its `reason` code; it never decides whether a code is valid.
 */
object PromoErrorMessages {

    const val REASON_NOT_FOUND = "not_found"
    const val REASON_INACTIVE = "inactive"
    const val REASON_NOT_STARTED = "not_started"
    const val REASON_EXPIRED = "expired"
    const val REASON_QUOTA_EXHAUSTED = "quota_exhausted"
    const val REASON_PER_USER_LIMIT = "per_user_limit"
    const val REASON_MIN_TRANSACTION = "min_transaction"
    const val REASON_NOT_APPLICABLE = "not_applicable"

    const val FALLBACK = "This promo code can't be applied. Check the code and try again."

    private val reasonPattern = Regex("\"reason\"\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"")
    private val messagePattern = Regex("\"message\"\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"")

    fun messageForReason(reason: String?): String = when (reason) {
        REASON_NOT_FOUND -> "This promo code doesn't exist. Check the spelling and try again."
        REASON_INACTIVE -> "This promo code is no longer active."
        REASON_NOT_STARTED -> "This promo code isn't active yet. Try again later."
        REASON_EXPIRED -> "This promo code has expired."
        REASON_QUOTA_EXHAUSTED -> "This promo code has reached its usage limit."
        REASON_PER_USER_LIMIT -> "You've already used this promo code."
        REASON_MIN_TRANSACTION -> "Your booking doesn't meet this promo's minimum spend."
        REASON_NOT_APPLICABLE -> "This promo code isn't valid for this tour."
        else -> FALLBACK
    }

    /** Extracts `reason` from an error body without needing a JSON parser. */
    fun reasonFromBody(body: String?): String? = extract(reasonPattern, body)

    /**
     * Prefers the reason-specific copy and falls back to the server's own
     * message (for request validation errors) before the generic fallback.
     */
    fun messageFromBody(body: String?, fallback: String = FALLBACK): String {
        val reason = reasonFromBody(body)
        if (reason != null) {
            val mapped = messageForReason(reason)
            if (mapped != FALLBACK) return mapped
        }

        return extract(messagePattern, body)?.takeIf { it.isNotBlank() } ?: fallback
    }

    private fun extract(pattern: Regex, body: String?): String? {
        if (body.isNullOrBlank()) return null

        val raw = pattern.find(body)?.groupValues?.getOrNull(1) ?: return null

        return raw
            .replace("\\\"", "\"")
            .replace("\\\\", "\\")
            .replace("\\n", "\n")
            .trim()
    }
}
