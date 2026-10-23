package com.naylaaisyah.traveloapp.data.tickets

import android.content.Context
import com.naylaaisyah.traveloapp.util.Constants
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Persists the active-ticket snapshot across screen visits and app restarts.
 *
 * Uses the same preferences file as the session, so logging out clears the
 * snapshot with the rest of the traveller's local state. Only booking ids are
 * stored; no ticket token, QR payload or personal data.
 */
@Singleton
class TicketUsageNoticeStore @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs = context.getSharedPreferences(
        Constants.PREF_NAME,
        Context.MODE_PRIVATE
    )

    fun previousActiveIds(userId: String?): Set<Int> {
        return prefs.getStringSet(key(userId), emptySet())
            .orEmpty()
            .mapNotNull(String::toIntOrNull)
            .toSet()
    }

    fun saveActiveIds(userId: String?, ids: Set<Int>) {
        val storageKey = key(userId)

        if (ids.isEmpty()) {
            prefs.edit().remove(storageKey).apply()
            return
        }

        prefs.edit()
            .putStringSet(storageKey, ids.map(Int::toString).toSet())
            .apply()
    }

    private fun key(userId: String?): String {
        return "ticket_active_ids_${userId?.takeIf { it.isNotBlank() } ?: "anonymous"}"
    }
}
