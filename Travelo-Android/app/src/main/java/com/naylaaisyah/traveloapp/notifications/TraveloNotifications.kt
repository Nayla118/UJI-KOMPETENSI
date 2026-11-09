package com.naylaaisyah.traveloapp.notifications

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.naylaaisyah.traveloapp.MainActivity
import com.naylaaisyah.traveloapp.R

/**
 * Notification channel + system notification presentation.
 *
 * The channel id must stay in sync with the backend's
 * `PushNotificationService::CHANNEL_ID` (`travelo_updates`).
 */
object TraveloNotifications {
    const val CHANNEL_ID = "travelo_updates"

    fun createChannel(context: Context) {
        val manager = context.getSystemService(NotificationManager::class.java) ?: return
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            "Travelo updates",
            NotificationManager.IMPORTANCE_DEFAULT
        ).apply {
            description = "Payment, ticket, trip reminder, and booking change updates."
        }

        manager.createNotificationChannel(channel)
    }

    fun show(
        context: Context,
        title: String,
        body: String,
        data: Map<String, String> = emptyMap()
    ) {
        createChannel(context)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.POST_NOTIFICATIONS
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        // The target is part of the PendingIntent identity so two different
        // notifications cannot overwrite each other's extras. CLEAR_TOP +
        // SINGLE_TOP delivers a warm start through MainActivity.onNewIntent().
        val requestCode = data.hashCode()
        val resultIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                Intent.FLAG_ACTIVITY_CLEAR_TOP or
                Intent.FLAG_ACTIVITY_SINGLE_TOP
            data.forEach { (key, value) -> putExtra(key, value) }

            if (!data.containsKey(DeepLinkParser.EXTRA_ID)) {
                val targetId = data[DeepLinkParser.EXTRA_BOOKING_ID]
                    ?: data[DeepLinkParser.EXTRA_DESTINATION_ID]
                    ?: data[DeepLinkParser.EXTRA_REVIEW_ID]
                targetId?.let { putExtra(DeepLinkParser.EXTRA_ID, it) }
            }
        }

        val pendingIntent = PendingIntent.getActivity(
            context,
            requestCode,
            resultIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()

        NotificationManagerCompat.from(context).notify(requestCode, notification)
    }
}
