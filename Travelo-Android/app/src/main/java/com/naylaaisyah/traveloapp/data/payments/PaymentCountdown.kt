package com.naylaaisyah.traveloapp.data.payments

import java.time.Duration
import java.time.Instant
import java.time.OffsetDateTime
import java.time.format.DateTimeParseException
import java.util.Locale

/**
 * Server-anchored payment countdown.
 *
 * The device wall clock is never trusted: the backend sends the remaining
 * seconds computed with its own clock, and the UI subtracts elapsed
 * *monotonic* time. When the precomputed value is missing, the absolute
 * `payment_expires_at` + `server_time` pair is used as the anchor.
 *
 * Pure functions only, so the maths/formatting are unit-testable without a
 * Compose runtime or an emulator.
 */
object PaymentCountdown {

    /** Required by the product copy for an elapsed payment window. */
    const val EXPIRED_LABEL = "Kedaluwarsa"

    /**
     * Remaining whole seconds after [elapsedMillis] of monotonic time.
     * A null server value means "unknown" and is reported as 0.
     */
    fun remainingSeconds(serverRemainingSeconds: Int?, elapsedMillis: Long = 0L): Long {
        if (serverRemainingSeconds == null) return 0L

        val elapsed = elapsedMillis.coerceAtLeast(0L) / 1000L

        return (serverRemainingSeconds.toLong() - elapsed).coerceAtLeast(0L)
    }

    /**
     * `payment_expires_at - server_time`, both supplied by the same backend
     * response. Returns null when either timestamp is missing/unparseable.
     */
    fun remainingSecondsBetween(deadlineIso: String?, serverTimeIso: String?): Long? {
        val deadline = parseInstant(deadlineIso) ?: return null
        val serverNow = parseInstant(serverTimeIso) ?: return null

        return Duration.between(serverNow, deadline).seconds.coerceAtLeast(0L)
    }

    /**
     * Prefer the server's precomputed remaining seconds; fall back to the
     * absolute deadline paired with the server clock. Returns null when the
     * booking has no payment window at all (legacy rows).
     */
    fun resolveRemaining(
        serverRemainingSeconds: Int?,
        deadlineIso: String? = null,
        serverTimeIso: String? = null,
        elapsedMillis: Long = 0L
    ): Long? {
        if (serverRemainingSeconds != null) {
            return remainingSeconds(serverRemainingSeconds, elapsedMillis)
        }

        val anchored = remainingSecondsBetween(deadlineIso, serverTimeIso) ?: return null
        val elapsed = elapsedMillis.coerceAtLeast(0L) / 1000L

        return (anchored - elapsed).coerceAtLeast(0L)
    }

    /**
     * The server flag wins. A zero remaining time also counts as expired, so a
     * countdown that reached 00:00 cannot leave the pay button enabled.
     */
    fun isExpired(serverExpired: Boolean?, remainingSeconds: Long?): Boolean {
        if (serverExpired == true) return true

        return remainingSeconds != null && remainingSeconds <= 0L
    }

    /** `mm:ss`. Minutes are not capped, so a long TTL renders as e.g. 120:00. */
    fun format(seconds: Long): String {
        val safe = seconds.coerceAtLeast(0L)
        val minutes = safe / 60L
        val rest = safe % 60L

        return String.format(Locale.US, "%02d:%02d", minutes, rest)
    }

    /** Countdown text, or the required "Kedaluwarsa" label once elapsed. */
    fun formatOrExpired(remainingSeconds: Long?): String {
        return if (remainingSeconds == null || remainingSeconds <= 0L) {
            EXPIRED_LABEL
        } else {
            format(remainingSeconds)
        }
    }

    /**
     * Accepts Laravel's Zulu ISO-8601 (`2026-10-25T04:00:00Z`, with or without
     * fractional seconds) and offset timestamps. Returns null on invalid input
     * instead of throwing inside a render pass.
     */
    fun parseInstant(value: String?): Instant? {
        if (value.isNullOrBlank()) return null

        return try {
            Instant.parse(value)
        } catch (_: DateTimeParseException) {
            try {
                OffsetDateTime.parse(value).toInstant()
            } catch (_: DateTimeParseException) {
                null
            }
        }
    }
}
