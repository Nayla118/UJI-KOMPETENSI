package com.naylaaisyah.traveloapp.util

import android.content.Context
import android.content.SharedPreferences
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class SessionManager @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs: SharedPreferences = context.getSharedPreferences(
        Constants.PREF_NAME,
        Context.MODE_PRIVATE
    )

    fun saveAuthToken(token: String) {
        prefs.edit().putString(Constants.PREF_AUTH_TOKEN, token).apply()
    }

    var authToken: String?
        get() = prefs.getString(Constants.PREF_AUTH_TOKEN, null)
        set(value) {
            prefs.edit().putString(Constants.PREF_AUTH_TOKEN, value).apply()
        }

    fun saveUserId(userId: Int) {
        prefs.edit().putInt(Constants.PREF_USER_ID, userId).apply()
    }

    fun getUserId(): Int {
        return prefs.getInt(Constants.PREF_USER_ID, 0)
    }

    var userId: String?
        get() = prefs.getString("user_id_string", null)
        set(value) {
            prefs.edit().putString("user_id_string", value).apply()
        }

    fun saveUserEmail(email: String) {
        prefs.edit().putString(Constants.PREF_USER_EMAIL, email).apply()
    }

    var userEmail: String?
        get() = prefs.getString(Constants.PREF_USER_EMAIL, null)
        set(value) {
            prefs.edit().putString(Constants.PREF_USER_EMAIL, value).apply()
        }

    fun saveUserName(name: String) {
        prefs.edit().putString("user_name", name).apply()
    }

    var userName: String?
        get() = prefs.getString("user_name", null)
        set(value) {
            prefs.edit().putString("user_name", value).apply()
        }

    fun saveUserPhoto(photoUrl: String?) {
        prefs.edit().putString("user_photo", photoUrl).apply()
    }

    var userPhoto: String?
        get() = prefs.getString("user_photo", null)
        set(value) {
            prefs.edit().putString("user_photo", value).apply()
        }

    fun clearSession() {
        prefs.edit().clear().apply()
    }

    fun isLoggedIn(): Boolean {
        return authToken != null
    }
}
