package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import android.graphics.Bitmap
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.ETicket
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * Backend-verified e-ticket.
 *
 * The reference and signed QR payload come from `/api/bookings/{id}/e-ticket`
 * after the server checks ownership and a confirmed payment. The app never
 * generates a ticket from a local booking id.
 */
@Composable
fun ETicketDialog(
    booking: Booking,
    ticket: ETicket,
    onDismiss: () -> Unit
) {
    val qrCode = remember(ticket.ticketToken) {
        generateQrCode(ticket.ticketToken, 720)
    }
    val tourTitle = ticket.tourTitle
        ?: booking.tourPackage?.safeTitle
        ?: stringResource(R.string.eticket_tour_package)
    val travelDate = ticket.bookingDate ?: booking.bookingDate
    val travelers = ticket.peopleCount ?: booking.peopleCount

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
                modifier = Modifier
                    .verticalScroll(rememberScrollState())
                    .padding(TraveloSpacing.lg),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = stringResource(R.string.eticket_title),
                            style = MaterialTheme.typography.titleLarge,
                            color = PlumInk
                        )
                        Text(
                            text = ticket.reference,
                            style = MaterialTheme.typography.labelMedium,
                            color = Rosewood
                        )
                    }

                    TraveloIconButton(
                        icon = Icons.Default.Close,
                        contentDescription = stringResource(R.string.eticket_close),
                        onClick = onDismiss
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.sm))

                DashedDivider()

                Spacer(Modifier.height(TraveloSpacing.md))

                Surface(
                    shape = CardShape,
                    color = VanillaCream,
                    border = BorderStroke(1.dp, PlumBorder)
                ) {
                    if (qrCode != null) {
                        Image(
                            bitmap = qrCode,
                            contentDescription = stringResource(
                                R.string.eticket_qr_description,
                                ticket.reference
                            ),
                            modifier = Modifier
                                .padding(TraveloSpacing.sm)
                                .size(180.dp)
                        )
                    } else {
                        Text(
                            text = stringResource(R.string.eticket_qr_error),
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(TraveloSpacing.lg)
                        )
                    }
                }

                Spacer(Modifier.height(TraveloSpacing.md))

                Text(
                    text = tourTitle,
                    style = MaterialTheme.typography.titleMedium,
                    color = PlumInk,
                    textAlign = TextAlign.Center
                )

                ticket.holderName?.takeIf { it.isNotBlank() }?.let { holder ->
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = holder,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted,
                        textAlign = TextAlign.Center
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.xs))

                Surface(shape = PillShape, color = SageTint) {
                    Row(
                        modifier = Modifier.padding(horizontal = TraveloSpacing.xs, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.CheckCircle,
                            contentDescription = null,
                            tint = SageDeep,
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(Modifier.width(4.dp))
                        Text(
                            text = stringResource(R.string.eticket_verified),
                            style = MaterialTheme.typography.labelSmall,
                            color = SageDeep
                        )
                    }
                }

                Spacer(Modifier.height(TraveloSpacing.md))

                TicketRow(
                    label = stringResource(R.string.eticket_travel_date),
                    value = formatDisplayDate(travelDate)
                )
                TicketRow(
                    label = stringResource(R.string.eticket_travelers_label),
                    value = pluralStringResource(
                        R.plurals.eticket_travelers,
                        travelers,
                        travelers
                    )
                )
                TicketRow(
                    label = stringResource(R.string.eticket_duration),
                    value = booking.tourPackage?.durationDays?.let { days ->
                        pluralStringResource(R.plurals.eticket_days, days, days)
                    } ?: "-"
                )
                TicketRow(
                    label = stringResource(R.string.eticket_booking_id),
                    value = "#${booking.id}"
                )
                TicketRow(
                    label = stringResource(R.string.eticket_total_paid),
                    value = formatRupiah(booking.totalPrice)
                )

                Spacer(Modifier.height(TraveloSpacing.md))

                Text(
                    text = stringResource(R.string.eticket_issued_note),
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted,
                    textAlign = TextAlign.Center
                )

                Spacer(Modifier.height(TraveloSpacing.md))

                TraveloButton(
                    text = stringResource(R.string.eticket_done),
                    onClick = onDismiss,
                    variant = TraveloButtonVariant.Secondary
                )
            }
        }
    }
}

@Composable
fun ETicketLoadingDialog(onDismiss: () -> Unit) {
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = CardShape, color = SurfaceWhite) {
            Column(
                modifier = Modifier.padding(TraveloSpacing.lg),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                CircularProgressIndicator(color = Rosewood)
                Spacer(Modifier.height(TraveloSpacing.sm))
                Text(
                    text = stringResource(R.string.eticket_verifying),
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk,
                    textAlign = TextAlign.Center
                )
            }
        }
    }
}

@Composable
private fun TicketRow(label: String, value: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
        Spacer(Modifier.width(TraveloSpacing.md))
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumInk,
            textAlign = TextAlign.End,
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
private fun DashedDivider() {
    Canvas(
        modifier = Modifier
            .fillMaxWidth()
            .height(1.dp)
    ) {
        drawLine(
            color = PlumBorder,
            start = Offset(0f, 0f),
            end = Offset(size.width, 0f),
            strokeWidth = 1.dp.toPx(),
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 8f), 0f)
        )
    }
}

private fun generateQrCode(payload: String, sizePx: Int): ImageBitmap? = runCatching {
    val hints = mapOf<EncodeHintType, Any>(
        EncodeHintType.MARGIN to 1,
        EncodeHintType.CHARACTER_SET to "UTF-8"
    )
    val matrix = QRCodeWriter().encode(
        payload,
        BarcodeFormat.QR_CODE,
        sizePx,
        sizePx,
        hints
    )

    val pixels = IntArray(sizePx * sizePx)
    val ink = 0xFF493747.toInt()
    val paper = 0xFFFFFFFF.toInt()

    for (y in 0 until sizePx) {
        for (x in 0 until sizePx) {
            pixels[y * sizePx + x] = if (matrix[x, y]) ink else paper
        }
    }

    Bitmap.createBitmap(sizePx, sizePx, Bitmap.Config.ARGB_8888).apply {
        setPixels(pixels, 0, sizePx, 0, 0, sizePx, sizePx)
    }.asImageBitmap()
}.getOrNull()
