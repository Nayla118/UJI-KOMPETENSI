package com.naylaaisyah.traveloapp.notifications

import android.util.Log
import com.google.firebase.messaging.FirebaseMessaging
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import kotlinx.coroutines.tasks.await
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Keeps the backend's device-token table in sync with Firebase.
 *
 * Registration is best-effort: a missing Play Services installation or an
 * offline device must never block sign-in, logout, or navigation. Push is an
 * enhancement; the app still refreshes data from the API on its own.
 */
@Singleton
class PushRegistrar @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager,
    private val tokenStore: PushTokenStore
) {
    /**
     * Registers this device for the signed-in account. Safe to call on every
     * app start: an unchanged (user, token) pair is a no-op.
     */
    suspend fun syncIfSignedIn() {
        val authToken = sessionManager.authToken ?: return
        val userId = sessionManager.userId ?: return

        val fcmToken = currentToken() ?: return

        if (tokenStore.registeredUserId == userId &&
            tokenStore.registeredToken == fcmToken
        ) {
            return
        }

        when (val result = repository.registerDeviceToken(authToken, fcmToken)) {
            is Resource.Success -> {
                tokenStore.registeredUserId = userId
                tokenStore.registeredToken = fcmToken
            }
            is Resource.Error -> Log.w(
                TAG,
                "Device token registration skipped: ${result.message}"
            )
            else -> Unit
        }
    }

    /** Called by the messaging service when Firebase rotates the token. */
    suspend fun onTokenRefreshed(token: String) {
        tokenStore.token = token
        tokenStore.clearRegistration()
        syncIfSignedIn()
    }

    /**
     * Unregisters the device before the local session is cleared.
     *
     * The caller captures the auth/FCM tokens first because clearing the
     * session removes the token this request needs.
     */
    suspend fun unregisterCurrent(authToken: String?, fcmToken: String?) {
        tokenStore.clearRegistration()

        if (authToken.isNullOrBlank() || fcmToken.isNullOrBlank()) return

        when (val result = repository.unregisterDeviceToken(authToken, fcmToken)) {
            is Resource.Success -> Log.d(TAG, "Device token unregistered")
            is Resource.Error -> Log.w(
                TAG,
                "Device token unregistration skipped: ${result.message}"
            )
            else -> Unit
        }
    }

    private suspend fun currentToken(): String? {
        tokenStore.token?.let { return it }

        return try {
            val token = FirebaseMessaging.getInstance().token.await()
            tokenStore.token = token
            token
        } catch (e: Exception) {
            // Emulators without Play Services land here; it is not fatal.
            Log.w(TAG, "FCM token unavailable: ${e.message}")
            null
        }
    }

    private companion object {
        const val TAG = "PushRegistrar"
    }
}
