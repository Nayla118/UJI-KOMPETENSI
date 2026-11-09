package com.naylaaisyah.traveloapp.ui.components

import android.os.SystemClock
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.data.payments.PaymentCountdown
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import kotlinx.coroutines.delay

/**
 * Server-anchored countdown state.
 *
 * The initial value comes from `payment_expires_in_seconds`; afterwards only
 * monotonic elapsed time ([SystemClock.elapsedRealtime]) is subtracted. The
 * device's wall clock/timezone is never consulted. A fresh server value
 * re-anchors the countdown automatically.
 *
 * Returns null when the booking has no payment window (legacy rows).
 */
@Composable
fun rememberPaymentRemainingSeconds(
    serverRemainingSeconds: Int?,
    serverExpired: Boolean?
): Long? {
    val key = serverRemainingSeconds to serverExpired
    var remaining by remember(key) {
        mutableStateOf(
            when {
                serverExpired == true -> 0L
                serverRemainingSeconds != null ->
                    PaymentCountdown.remainingSeconds(serverRemainingSeconds, 0L)
                else -> null
            }
        )
    }

    LaunchedEffect(key) {
        val anchor = serverRemainingSeconds

        if (anchor == null || serverExpired == true || anchor <= 0) {
            remaining = if (serverExpired == true || anchor != null) 0L else null
            return@LaunchedEffect
        }

        val startedAt = SystemClock.elapsedRealtime()

        while (true) {
            val value = PaymentCountdown.resolveRemaining(
                serverRemainingSeconds = anchor,
                elapsedMillis = SystemClock.elapsedRealtime() - startedAt
            ) ?: 0L

            remaining = value

            if (value <= 0L) break

            delay(250L)
        }
    }

    return remaining
}

/**
 * Deadline banner used by the payment screen. Shows the mm:ss countdown while
 * the window is open and the required "Kedaluwarsa" state once it closes.
 */
@Composable
fun PaymentDeadlineNotice(
    remainingSeconds: Long?,
    expired: Boolean,
    modifier: Modifier = Modifier
) {
    val accent = if (expired) DangerDeep else WarningDeep
    val background = if (expired) DangerTint else WarningTint

    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = MaterialTheme.shapes.medium,
        color = background,
        border = BorderStroke(1.dp, accent.copy(alpha = 0.15f))
    ) {
        Row(
            modifier = Modifier.padding(TraveloSpacing.sm),
            verticalAlignment = Alignment.Top
        ) {
            Icon(
                imageVector = Icons.Default.Schedule,
                contentDescription = null,
                tint = accent,
                modifier = Modifier.size(18.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.xs))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = if (expired) {
                        PaymentCountdown.EXPIRED_LABEL
                    } else {
                        "Pay within ${PaymentCountdown.format(remainingSeconds ?: 0L)}"
                    },
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = if (expired) {
                        "The payment window has closed and your seats were released. " +
                            "Book again to reserve them."
                    } else {
                        "Your seats are held until the timer runs out. " +
                            "The countdown uses the server clock."
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }
    }
}
