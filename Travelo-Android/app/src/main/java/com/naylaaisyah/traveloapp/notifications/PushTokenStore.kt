package com.naylaaisyah.traveloapp.notifications

import android.content.Context
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * FCM token persistence, kept in its own preferences file.
 *
 * [com.naylaaisyah.traveloapp.util.SessionManager.clearSession] wipes the auth
 * preferences on logout. The FCM token must survive that long enough for the
 * backend to unregister the device, so it lives here instead.
 */
@Singleton
class PushTokenStore @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    /** Latest token issued by Firebase for this install. */
    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) {
            prefs.edit().putString(KEY_TOKEN, value).apply()
        }

    /** Account the current token was last registered to (empty = none). */
    var registeredUserId: String?
        get() = prefs.getString(KEY_REGISTERED_USER, null)
        set(value) {
            prefs.edit().putString(KEY_REGISTERED_USER, value).apply()
        }

    /** Token value that was last confirmed by the backend. */
    var registeredToken: String?
        get() = prefs.getString(KEY_REGISTERED_TOKEN, null)
        set(value) {
            prefs.edit().putString(KEY_REGISTERED_TOKEN, value).apply()
        }

    fun clearRegistration() {
        prefs.edit()
            .remove(KEY_REGISTERED_USER)
            .remove(KEY_REGISTERED_TOKEN)
            .apply()
    }

    private companion object {
        const val PREFS_NAME = "travelo_push"
        const val KEY_TOKEN = "fcm_token"
        const val KEY_REGISTERED_USER = "registered_user_id"
        const val KEY_REGISTERED_TOKEN = "registered_token"
    }
}
