package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.RefundPreview
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloTextField
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * Cancellation is a request that an admin must approve. The copy says so
 * explicitly so nobody expects the booking to disappear immediately.
 */
@Composable
fun CancellationRequestDialog(
    booking: Booking,
    viewModel: MyBookingsViewModel,
    isSubmitting: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (reason: String?) -> Unit
) {
    var reason by remember { mutableStateOf("") }

    // Load the refund estimate when the dialog opens.
    var previewLoaded by remember { mutableStateOf(false) }
    if (!previewLoaded) {
        viewModel.loadRefundPreview(booking.id)
        previewLoaded = true
    }

    val preview = viewModel.uiState.collectAsState().value.refundPreviewFor[booking.id]

    Dialog(
        onDismissRequest = { if (!isSubmitting) onDismiss() },
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.md),
            shape = HeroShape,
            color = SurfaceWhite
        ) {
            Column(modifier = Modifier.padding(TraveloSpacing.lg)) {
                Text(
                    text = "Cancel this booking?",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xxs))
                Text(
                    text = booking.tourPackage?.safeTitle ?: "Booking #${booking.id}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk
                )
                Text(
                    text = "Visit date: ${formatDisplayDate(booking.bookingDate)}",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )

                Spacer(Modifier.height(TraveloSpacing.sm))

                // Refund estimate card.
                if (preview != null) {
                    RefundPreviewCard(preview = preview)
                    Spacer(Modifier.height(TraveloSpacing.sm))
                }

                Text(
                    text = "Our team reviews every cancellation. If it is approved, the booking is cancelled and any refund is processed according to our cancellation policy.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )

                Spacer(Modifier.height(TraveloSpacing.sm))
                TraveloTextField(
                    value = reason,
                    onValueChange = { if (it.length <= 1000) reason = it },
                    label = "Reason (optional)",
                    placeholder = "Tell us why you need to cancel",
                    singleLine = false,
                    enabled = !isSubmitting
                )

                Spacer(Modifier.height(TraveloSpacing.lg))
                TraveloButton(
                    text = "Send cancellation request",
                    onClick = { onConfirm(reason.trim().takeIf { it.isNotEmpty() }) },
                    loading = isSubmitting,
                    enabled = !isSubmitting,
                    size = TraveloButtonSize.Medium
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                TraveloButton(
                    text = "Keep booking",
                    onClick = onDismiss,
                    enabled = !isSubmitting,
                    variant = TraveloButtonVariant.Secondary,
                    size = TraveloButtonSize.Medium
                )
            }
        }
    }
}

/**
 * Shows the refund estimate: percentage, amount, and tier explanation.
 */
@Composable
private fun RefundPreviewCard(preview: RefundPreview) {
    val eligible = preview.eligible
    val amountText = if (eligible) formatRupiah(preview.refundAmount) else "IDR 0"

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(TraveloSpacing.md),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = androidx.compose.foundation.layout.Arrangement.SpaceBetween
        ) {
            Text(
                text = "Estimated refund",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumInk
            )
            Text(
                text = if (eligible) amountText else "Not eligible",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumInk
            )
        }
        Spacer(Modifier.height(TraveloSpacing.xxs))
        Text(
            text = if (eligible) {
                "Up to ${preview.percentage}% of the amount you paid (${preview.tierLabel})."
            } else {
                preview.reason ?: "No refund available for this booking."
            },
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
        Spacer(Modifier.height(TraveloSpacing.xs))
        Text(
            text = "${preview.daysBefore} day(s) before your trip · Paid: ${formatRupiah(preview.totalPrice)}" +
                (if (preview.promoCode != null) " (promo applied)" else ""),
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
    }
}
