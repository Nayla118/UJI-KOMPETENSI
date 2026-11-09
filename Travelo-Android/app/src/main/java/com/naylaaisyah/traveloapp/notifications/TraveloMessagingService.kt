package com.naylaaisyah.traveloapp.notifications

import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Receives FCM messages and handles token rotation at any time.
 *
 * The backend sends data-only messages, so this service builds every
 * notification in the foreground, background, and cold-start cases. That is
 * what lets the notification's PendingIntent always carry the deep-link extras
 * (type, screen, and id) instead of letting the Firebase SDK auto-display a
 * notification without them.
 */
@AndroidEntryPoint
class TraveloMessagingService : FirebaseMessagingService() {

    @Inject
    lateinit var pushRegistrar: PushRegistrar

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNewToken(token: String) {
        super.onNewToken(token)

        // The token can rotate while no activity is visible; persist it and
        // refresh the backend registration if somebody is signed in.
        scope.launch {
            pushRegistrar.onTokenRefreshed(token)
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)

        val title = message.notification?.title
            ?: message.data["title"]
            ?: "Travelo"
        val body = message.notification?.body
            ?: message.data["body"]
            ?: message.data["message"]

        if (body.isNullOrBlank()) {
            Log.d("TraveloMessaging", "Data-only message without body ignored")
            return
        }

        TraveloNotifications.show(
            context = this,
            title = title,
            body = body,
            data = message.data
        )
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }
}
