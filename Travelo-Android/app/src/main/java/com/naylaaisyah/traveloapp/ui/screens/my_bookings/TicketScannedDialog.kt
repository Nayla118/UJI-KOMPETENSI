package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.History
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing

/**
 * One-time notice that the backend changed an active ticket to used.
 *
 * Shown only for a ticket that was active on the previous My Bookings load, so
 * an old ticket in history never triggers a false "just scanned" message.
 */
@Composable
fun TicketScannedDialog(
    booking: Booking,
    onViewHistory: () -> Unit,
    onDismiss: () -> Unit
) {
    val tourTitle = booking.tourPackage?.safeTitle
        ?: stringResource(R.string.ticket_history_tour_fallback)

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.md),
            shape = HeroShape,
            color = SurfaceWhite
        ) {
            Column(
                modifier = Modifier.padding(TraveloSpacing.lg),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Surface(shape = PillShape, color = SageTint) {
                    Icon(
                        imageVector = Icons.Default.CheckCircle,
                        contentDescription = null,
                        tint = SageDeep,
                        modifier = Modifier
                            .padding(12.dp)
                            .size(34.dp)
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.sm))

                Text(
                    text = stringResource(R.string.ticket_scanned_title),
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk,
                    textAlign = TextAlign.Center
                )

                Spacer(Modifier.height(TraveloSpacing.xs))

                Text(
                    text = stringResource(R.string.ticket_scanned_message, tourTitle),
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumMuted,
                    textAlign = TextAlign.Center
                )

                Spacer(Modifier.height(TraveloSpacing.lg))

                TraveloButton(
                    text = stringResource(R.string.ticket_scanned_view_history),
                    onClick = onViewHistory,
                    leadingIcon = Icons.Default.History
                )

                Spacer(Modifier.height(TraveloSpacing.xs))

                TraveloButton(
                    text = stringResource(R.string.ticket_scanned_dismiss),
                    onClick = onDismiss,
                    variant = TraveloButtonVariant.Secondary
                )
            }
        }
    }
}
