package com.naylaaisyah.traveloapp.ui.screens.payment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.displayMethod
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * Terminal payment result rendered from backend truth only.
 * Plain language, no technical jargon.
 */
@Composable
fun PaymentResultScreen(
    bookingId: Int,
    onViewMyBookings: () -> Unit,
    onPayAgain: (Int) -> Unit,
    viewModel: PaymentResultViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(bookingId) {
        viewModel.loadResult(bookingId)
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Payment result",
                onBackClick = onViewMyBookings
            )
        },
        containerColor = VanillaCream
    ) { paddingValues ->
        when {
            uiState.isLoading -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .padding(horizontal = TraveloSpacing.screen)
                ) {
                    TraveloLoadingState()
                }
            }

            uiState.booking == null -> {
                TraveloErrorState(
                    message = uiState.error ?: "Could not load payment result.",
                    onRetry = { viewModel.loadResult(bookingId) },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = "Couldn't load this payment result",
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            else -> {
                val booking = uiState.booking!!
                val method = booking.payment?.displayMethod()
                ResultBody(
                    modifier = Modifier.padding(paddingValues),
                    outcome = uiState.outcome,
                    bookingId = booking.id,
                    tourTitle = booking.tourPackage?.title ?: "Tour package",
                    total = booking.totalPrice,
                    method = method,
                    onViewMyBookings = onViewMyBookings,
                    onPayAgain = { onPayAgain(booking.id) },
                    onRefresh = { viewModel.loadResult(booking.id) }
                )
            }
        }
    }
}

private data class ResultVisual(
    val icon: ImageVector,
    val tint: Color,
    val background: Color,
    val title: String,
    val message: String
)

@Composable
private fun ResultBody(
    modifier: Modifier = Modifier,
    outcome: PaymentOutcome,
    bookingId: Int,
    tourTitle: String,
    total: Double,
    method: String?,
    onViewMyBookings: () -> Unit,
    onPayAgain: () -> Unit,
    onRefresh: () -> Unit
) {
    val visual = when (outcome) {
        PaymentOutcome.SUCCESS -> ResultVisual(
            Icons.Default.CheckCircle,
            SageDeep,
            SageTint,
            "Payment successful",
            "Your booking is confirmed. You can find it in My bookings."
        )

        PaymentOutcome.WAITING -> ResultVisual(
            Icons.Default.HourglassEmpty,
            WarningDeep,
            WarningTint,
            "Payment not confirmed yet",
            "We haven't received a confirmed payment yet. If you just paid, give it a moment and check again."
        )

        PaymentOutcome.FAILED -> ResultVisual(
            Icons.Default.ErrorOutline,
            DangerDeep,
            DangerTint,
            "Payment failed",
            "The payment was not completed. You can start a new payment."
        )

        PaymentOutcome.EXPIRED -> ResultVisual(
            Icons.Default.Schedule,
            WarningDeep,
            WarningTint,
            "Payment expired",
            "The payment window closed before it was completed. Start a new payment to confirm your booking."
        )

        PaymentOutcome.CANCELLED -> ResultVisual(
            Icons.Default.Cancel,
            DangerDeep,
            DangerTint,
            "Payment cancelled",
            "This payment was cancelled before it completed."
        )
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.lg),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Surface(shape = CircleShape, color = visual.background) {
            Icon(
                imageVector = visual.icon,
                contentDescription = null,
                tint = visual.tint,
                modifier = Modifier
                    .padding(18.dp)
                    .size(36.dp)
            )
        }

        Spacer(Modifier.height(TraveloSpacing.md))

        Text(
            text = visual.title,
            style = MaterialTheme.typography.headlineSmall,
            color = PlumInk,
            textAlign = TextAlign.Center
        )

        Spacer(Modifier.height(TraveloSpacing.xs))

        Text(
            text = visual.message,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumMuted,
            textAlign = TextAlign.Center
        )

        Spacer(Modifier.height(TraveloSpacing.lg))

        TraveloCard {
            ResultRow(label = "Booking ID", value = "#$bookingId")
            Spacer(Modifier.height(TraveloSpacing.xs))
            ResultRow(label = "Tour", value = tourTitle)
            if (method != null && outcome == PaymentOutcome.SUCCESS) {
                Spacer(Modifier.height(TraveloSpacing.xs))
                ResultRow(label = "Paid with", value = method)
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = if (outcome == PaymentOutcome.SUCCESS) "Total paid" else "Total",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                TraveloPriceText(
                    amount = total,
                    compact = true,
                    emphasized = true
                )
            }
        }

        Spacer(Modifier.height(TraveloSpacing.lg))

        when (outcome) {
            PaymentOutcome.SUCCESS -> {
                TraveloButton(
                    text = "View my bookings",
                    onClick = onViewMyBookings
                )
            }

            PaymentOutcome.WAITING -> {
                TraveloButton(
                    text = "View my bookings",
                    onClick = onViewMyBookings
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                TraveloButton(
                    text = "Pay again",
                    onClick = onPayAgain,
                    variant = TraveloButtonVariant.Secondary
                )
                TextButton(onClick = onRefresh) {
                    Text(
                        text = "Check status again",
                        style = MaterialTheme.typography.labelLarge,
                        color = Rosewood
                    )
                }
            }

            PaymentOutcome.FAILED,
            PaymentOutcome.EXPIRED,
            PaymentOutcome.CANCELLED -> {
                TraveloButton(
                    text = if (outcome == PaymentOutcome.FAILED) "Try again" else "Pay again",
                    onClick = onPayAgain
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                TraveloButton(
                    text = "View my bookings",
                    onClick = onViewMyBookings,
                    variant = TraveloButtonVariant.Secondary
                )
            }
        }

        Spacer(Modifier.height(TraveloSpacing.lg))
    }
}

@Composable
private fun ResultRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
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
