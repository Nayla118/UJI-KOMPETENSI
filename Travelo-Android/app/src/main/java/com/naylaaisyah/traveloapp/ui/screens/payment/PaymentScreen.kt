package com.naylaaisyah.traveloapp.ui.screens.payment

import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
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
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah
import kotlinx.coroutines.launch

/**
 * BOOKING SUMMARY -> PAYMENT -> (Midtrans page) -> PROCESSING -> RESULT.
 *
 * This screen never decides success locally: after the Midtrans page
 * closes, it reconciles with the backend (single verification + short
 * bounded polls) and then navigates to the result screen, which renders
 * backend truth (success / waiting / failed / expired).
 */
@Composable
fun PaymentScreen(
    bookingId: Int,
    onBackClick: () -> Unit,
    onFinished: (Int) -> Unit,
    viewModel: PaymentViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(bookingId) {
        viewModel.loadBooking(bookingId)
    }

    val launcher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) {
        // ANY return from the payment page (success/pending/closed) must be
        // reconciled with the backend — never trust the page result alone.
        scope.launch {
            viewModel.reconcileAfterReturn(bookingId)
            onFinished(bookingId)
        }
    }

    LaunchedEffect(uiState.snapTokenToLaunch) {
        val token = uiState.snapTokenToLaunch ?: return@LaunchedEffect
        val intent = android.content.Intent(context, PaymentWebViewActivity::class.java).apply {
            putExtra(PaymentWebViewActivity.EXTRA_SNAP_TOKEN, token)
            putExtra(PaymentWebViewActivity.EXTRA_BOOKING_ID, bookingId)
            putExtra(PaymentWebViewActivity.EXTRA_IS_SANDBOX, true)
        }
        viewModel.onTokenConsumed()
        launcher.launch(intent)
    }

    LaunchedEffect(uiState.error) {
        uiState.error?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearError()
        }
    }

    BackHandler(enabled = uiState.isReconciling) {
        // Safe back during processing: stop waiting here, the result screen
        // will load the latest backend state.
        onFinished(bookingId)
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Payment",
                onBackClick = {
                    if (!uiState.isReconciling) onBackClick() else onFinished(bookingId)
                }
            )
        },
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        containerColor = VanillaCream
    ) { paddingValues ->
        when {
            uiState.isLoadingBooking -> {
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
                    message = uiState.error ?: "Could not load booking.",
                    onRetry = { viewModel.loadBooking(bookingId) },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = "Couldn't load this booking",
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            else -> {
                val booking = uiState.booking!!
                Box(modifier = Modifier.fillMaxSize()) {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(paddingValues)
                            .verticalScroll(rememberScrollState())
                            .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.sm)
                    ) {
                        BookingSummaryCard(
                            title = booking.tourPackage?.title ?: "Tour package",
                            date = booking.bookingDate,
                            people = booking.peopleCount,
                            total = booking.totalPrice
                        )

                        Spacer(Modifier.height(TraveloSpacing.md))

                        PaymentMethodCard()

                        Spacer(Modifier.height(TraveloSpacing.md))

                        HowItWorksCard()

                        Spacer(Modifier.height(TraveloSpacing.lg))

                        TraveloButton(
                            text = "Pay ${formatRupiah(booking.totalPrice)}",
                            onClick = { viewModel.startPayment() },
                            loading = uiState.isCreatingToken,
                            enabled = !uiState.isReconciling
                        )

                        Spacer(Modifier.height(TraveloSpacing.sm))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.Lock,
                                contentDescription = null,
                                tint = PlumMuted,
                                modifier = Modifier.size(14.dp)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text(
                                text = "Payment is processed securely by Midtrans.",
                                style = MaterialTheme.typography.bodySmall,
                                color = PlumMuted
                            )
                        }

                        Spacer(Modifier.height(TraveloSpacing.md))
                    }

                    if (uiState.isReconciling) {
                        ProcessingOverlay(
                            attempt = uiState.reconcileAttempt,
                            max = uiState.reconcileMax
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun BookingSummaryCard(title: String, date: String, people: Int, total: Double) {
    TraveloCard {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                imageVector = Icons.Default.ReceiptLong,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(24.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Text(
                text = "Booking summary",
                style = MaterialTheme.typography.titleMedium,
                color = PlumInk
            )
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        Text(
            text = title,
            style = MaterialTheme.typography.bodyLarge,
            color = PlumInk
        )

        Spacer(Modifier.height(TraveloSpacing.xs))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                imageVector = Icons.Default.CalendarMonth,
                contentDescription = null,
                tint = PlumMuted,
                modifier = Modifier.size(16.dp)
            )
            Spacer(Modifier.width(6.dp))
            Text(
                text = formatDisplayDate(date),
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
            Spacer(Modifier.width(TraveloSpacing.md))
            Icon(
                imageVector = Icons.Default.People,
                contentDescription = null,
                tint = PlumMuted,
                modifier = Modifier.size(16.dp)
            )
            Spacer(Modifier.width(6.dp))
            Text(
                text = if (people == 1) "1 traveler" else "$people travelers",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        Surface(shape = CardShape, color = BlushMist) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(TraveloSpacing.sm),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Total to pay",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                TraveloPriceText(amount = total, emphasized = true)
            }
        }
    }
}

@Composable
private fun PaymentMethodCard() {
    TraveloCard {
        Text(
            text = "Payment method",
            style = MaterialTheme.typography.titleMedium,
            color = PlumInk
        )
        Spacer(Modifier.height(TraveloSpacing.xxs))
        Text(
            text = "You'll pick a method on Midtrans' secure page. The options shown there depend on your Midtrans account and region.",
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
    }
}

@Composable
private fun HowItWorksCard() {
    TraveloCard {
        Text(
            text = "How it works",
            style = MaterialTheme.typography.titleMedium,
            color = PlumInk
        )
        Spacer(Modifier.height(TraveloSpacing.sm))
        PaymentStep(number = "1", text = "Choose a payment method on the secure page.")
        Spacer(Modifier.height(TraveloSpacing.xs))
        PaymentStep(number = "2", text = "Complete the payment as instructed.")
        Spacer(Modifier.height(TraveloSpacing.xs))
        PaymentStep(number = "3", text = "Return here — we check the status with the backend.")
    }
}

@Composable
private fun PaymentStep(number: String, text: String) {
    Row(verticalAlignment = Alignment.Top) {
        Surface(shape = CircleShape, color = BlushMist) {
            Text(
                text = number,
                style = MaterialTheme.typography.labelMedium,
                color = Rosewood,
                modifier = Modifier.padding(horizontal = 9.dp, vertical = 5.dp)
            )
        }
        Spacer(Modifier.width(TraveloSpacing.xs))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted,
            modifier = Modifier.padding(top = 3.dp)
        )
    }
}

@Composable
private fun ProcessingOverlay(attempt: Int, max: Int) {
    Surface(
        modifier = Modifier.fillMaxSize(),
        color = VanillaCream.copy(alpha = 0.97f)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(TraveloSpacing.xl),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            CircularProgressIndicator(
                color = Rosewood,
                modifier = Modifier.size(48.dp)
            )
            Spacer(Modifier.height(TraveloSpacing.lg))
            Text(
                text = "Confirming your payment",
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(TraveloSpacing.xs))
            Text(
                text = "This usually takes a few seconds. Please keep the app open while we check with the backend.",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(TraveloSpacing.lg))
            LinearProgressIndicator(
                progress = attempt.coerceAtMost(max).toFloat() / max.toFloat(),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(6.dp),
                color = Rosewood,
                trackColor = BlushMist
            )
            Spacer(Modifier.height(TraveloSpacing.xs))
            Text(
                text = "Checking ${attempt.coerceAtMost(max)} of $max",
                style = MaterialTheme.typography.labelMedium,
                color = PlumMuted
            )
        }
    }
}
