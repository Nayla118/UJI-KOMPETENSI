package com.naylaaisyah.traveloapp.data.recovery

import android.content.Context
import com.google.gson.Gson
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Visitor
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * The minimum metadata needed to resume identity-document uploads after the
 * app process is gone.
 *
 * Deliberately contains no identity number, document bytes, content URI,
 * original file name, auth token or private storage path. The backend is the
 * authority for which visitor still needs a document; this queue only keeps
 * the booking reference so the user can find it again.
 */
data class DocumentRecoveryJob(
    val bookingId: Int,
    val userId: String,
    val tourTitle: String,
    val peopleCount: Int,
    val createdAt: Long,
    val lastAttemptAt: Long? = null
)

/**
 * Pure queue/status rules, kept separate from Android storage so they can be
 * covered by fast JVM tests.
 */
object DocumentRecoveryRules {
    const val MAX_JOBS = 50

    fun upsert(
        jobs: List<DocumentRecoveryJob>,
        job: DocumentRecoveryJob
    ): List<DocumentRecoveryJob> {
        val withoutPrevious = jobs.filterNot {
            it.bookingId == job.bookingId && it.userId == job.userId
        }
        return (withoutPrevious + job)
            .sortedByDescending { it.createdAt }
            .take(MAX_JOBS)
    }

    fun remove(
        jobs: List<DocumentRecoveryJob>,
        bookingId: Int,
        userId: String
    ): List<DocumentRecoveryJob> = jobs.filterNot {
        it.bookingId == bookingId && it.userId == userId
    }

    /** Recovery jobs are scoped to the account that created the booking. */
    fun forUser(jobs: List<DocumentRecoveryJob>, userId: String): List<DocumentRecoveryJob> =
        jobs.filter { it.userId == userId }
            .sortedByDescending { it.createdAt }

    /**
     * Only visitors without a document on file need an upload. Pending and
     * verified documents are skipped, so a retry can never overwrite or
     * duplicate work the backend already accepted.
     */
    fun visitorsRequiringUpload(visitors: List<Visitor>): List<Visitor> =
        visitors.filter { !it.hasDocument }

    /**
     * Visitors that still need a document and do not have a fresh selection in
     * the current screen. A stale/revoked URI is not persisted, so after a
     * restart this starts as every missing visitor and becomes empty again
     * after the user reselects the files.
     */
    fun visitorsMissingSelection(
        visitors: List<Visitor>,
        selectedVisitorIds: Set<Int>
    ): List<Visitor> =
        visitorsRequiringUpload(visitors).filterNot { it.id in selectedVisitorIds }

    /**
     * Legacy bookings without visitor rows remain compatible: they never need
     * identity completion. Once visitors exist, every row and the expected
     * traveller count must have a document on file.
     */
    fun needsIdentityDocuments(booking: Booking): Boolean {
        val visitors = booking.visitors
        if (visitors.isEmpty()) return false
        if (visitors.size != booking.peopleCount) return true
        return visitors.any { !it.hasDocument }
    }
}

/** JSON codec for the recovery queue. No secrets are ever part of the model. */
object DocumentRecoveryCodec {
    private val gson = Gson()

    fun encode(jobs: List<DocumentRecoveryJob>): String = gson.toJson(jobs)

    fun decode(raw: String?): List<DocumentRecoveryJob> {
        if (raw.isNullOrBlank()) return emptyList()
        return runCatching {
            gson.fromJson(raw, Array<DocumentRecoveryJob>::class.java)?.toList().orEmpty()
        }.getOrElse { emptyList() }
    }
}

/**
 * Persists recovery jobs in a dedicated SharedPreferences file.
 *
 * This file is intentionally separate from [com.naylaaisyah.traveloapp.util.SessionManager]'s
 * preferences: logging out clears the auth session but must not erase a
 * booking that still needs documents. Access is always filtered by the
 * current backend user id, so another account on the device cannot see it.
 */
@Singleton
class DocumentRecoveryStore @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    private val lock = Any()

    fun markPending(job: DocumentRecoveryJob) = synchronized(lock) {
        write(DocumentRecoveryRules.upsert(read(), job))
    }

    fun markAttempt(bookingId: Int, userId: String, at: Long = System.currentTimeMillis()) {
        synchronized(lock) {
            val updated = read().map { job ->
                if (job.bookingId == bookingId && job.userId == userId) {
                    job.copy(lastAttemptAt = at)
                } else {
                    job
                }
            }
            write(updated)
        }
    }

    /** Called only after every required document has a document on file. */
    fun clear(bookingId: Int, userId: String) {
        synchronized(lock) {
            write(DocumentRecoveryRules.remove(read(), bookingId, userId))
        }
    }

    fun pendingFor(userId: String?): List<DocumentRecoveryJob> {
        if (userId.isNullOrBlank()) return emptyList()
        return synchronized(lock) {
            DocumentRecoveryRules.forUser(read(), userId)
        }
    }

    fun hasPendingFor(userId: String?): Boolean = pendingFor(userId).isNotEmpty()

    private fun read(): List<DocumentRecoveryJob> =
        DocumentRecoveryCodec.decode(prefs.getString(KEY_JOBS, null))

    private fun write(jobs: List<DocumentRecoveryJob>) {
        prefs.edit().putString(KEY_JOBS, DocumentRecoveryCodec.encode(jobs)).apply()
    }

    private companion object {
        const val PREFS_NAME = "travelo_document_recovery"
        const val KEY_JOBS = "jobs"
    }
}
