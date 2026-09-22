package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import android.app.Activity
import android.content.Intent
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Pending
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.AlertDialog
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.ErrorMessage
import com.naylaaisyah.traveloapp.ui.components.LoadingIndicator
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentWebViewActivity
import com.naylaaisyah.traveloapp.ui.theme.Background
import com.naylaaisyah.traveloapp.ui.theme.Error
import com.naylaaisyah.traveloapp.ui.theme.Gray200
import com.naylaaisyah.traveloapp.ui.theme.Gray400
import com.naylaaisyah.traveloapp.ui.theme.Gray500
import com.naylaaisyah.traveloapp.ui.theme.Gray600
import com.naylaaisyah.traveloapp.ui.theme.Gray700
import com.naylaaisyah.traveloapp.ui.theme.Primary
import com.naylaaisyah.traveloapp.ui.theme.Surface
import com.naylaaisyah.traveloapp.ui.theme.Success
import com.naylaaisyah.traveloapp.ui.theme.Warning

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyBookingsScreen(
    onBackClick: () -> Unit,
    viewModel: MyBookingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    val context = LocalContext.current

    val paymentLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        when (result.resultCode) {
            PaymentWebViewActivity.RESULT_PAYMENT_SUCCESS -> {
                viewModel.onPaymentSuccess()
            }
            PaymentWebViewActivity.RESULT_PAYMENT_PENDING -> {
                viewModel.onPaymentPending()
            }
            PaymentWebViewActivity.RESULT_PAYMENT_CANCELLED,
            Activity.RESULT_CANCELED -> {
                viewModel.onPaymentCancelled()
            }
            else -> {
                viewModel.onPaymentResult()
            }
        }
    }

    LaunchedEffect(Unit) {
        viewModel.loadBookings()
    }

    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            viewModel.loadBookings()
        }
    }

    LaunchedEffect(uiState.snapTokenToLaunch) {
        val snapToken = uiState.snapTokenToLaunch ?: return@LaunchedEffect
        val intent = Intent(
            context,
            PaymentWebViewActivity::class.java
        ).apply {
            putExtra(PaymentWebViewActivity.EXTRA_SNAP_TOKEN, snapToken)
            putExtra(PaymentWebViewActivity.EXTRA_IS_SANDBOX, true)
        }
        paymentLauncher.launch(intent)
        viewModel.onPaymentLaunchConsumed()
    }

    LaunchedEffect(uiState.paymentMessage) {
        uiState.paymentMessage?.let { message ->
            if (!message.contains("success", ignoreCase = true)) {
                snackbarHostState.showSnackbar(message)
                viewModel.clearMessage()
            }
        }
    }

    LaunchedEffect(uiState.error) {
        uiState.error?.let { message ->
            if (uiState.bookings.isNotEmpty()) {
                snackbarHostState.showSnackbar(message)
                viewModel.clearError()
            }
        }
    }

    if (uiState.showPaymentSuccessDialog) {
        PaymentSuccessDialog(
            onDismiss = { viewModel.dismissPaymentDialog() }
        )
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "My Bookings",
                onBackClick = onBackClick,
                actions = {
                    IconButton(onClick = { viewModel.loadBookings() }) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Refresh",
                            tint = Primary
                        )
                    }
                }
            )
        },
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        containerColor = Background
    ) { paddingValues ->
        when {
            uiState.isLoading && uiState.bookings.isEmpty() -> LoadingIndicator()
            uiState.error != null && uiState.bookings.isEmpty() -> ErrorMessage(
                message = uiState.error!!,
                onRetry = { viewModel.loadBookings() }
            )
            uiState.bookings.isEmpty() -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Icon(
                            imageVector = Icons.Default.ReceiptLong,
                            contentDescription = "No Bookings",
                            tint = Gray400,
                            modifier = Modifier.size(64.dp)
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Text(
                            text = "No bookings yet",
                            style = MaterialTheme.typography.titleMedium,
                            color = Gray600
                        )
                        Text(
                            text = "Your booking history will appear here",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Gray500
                        )
                    }
                }
            }
            else -> {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(uiState.bookings) { booking ->
                        BookingCard(
                            booking = booking,
                            isProcessingPayment = uiState.payingBookingId == booking.id,
                            onPayNowClick = {
                                viewModel.startPendingPayment(booking)
                            }
                        )
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BookingCard(
    booking: Booking,
    isProcessingPayment: Boolean,
    onPayNowClick: () -> Unit
) {
    val paymentStatus = booking.payment?.status?.lowercase()
    val isPendingPayment = booking.status.lowercase() == "pending" &&
        (paymentStatus == null || paymentStatus == "pending")

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Booking #${booking.id}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                BookingStatusChip(status = booking.status)
            }

            Spacer(modifier = Modifier.height(12.dp))

            booking.tourPackage?.let { tourPackage ->
                Text(
                    text = tourPackage.safeTitle,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.Medium
                )
            }

            Spacer(modifier = Modifier.height(8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.CalendarMonth,
                        contentDescription = "Date",
                        tint = Gray500,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = booking.bookingDate,
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.People,
                        contentDescription = "People",
                        tint = Gray500,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = "${booking.peopleCount} people",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                }
            }

            Divider(modifier = Modifier.padding(vertical = 8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Total: Rp ${String.format("%,.0f", booking.totalPrice)}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = Primary
                )

                PaymentStatusChip(status = paymentStatus)
            }

            if (isPendingPayment) {
                Spacer(modifier = Modifier.height(12.dp))
                PendingPaymentCallout(
                    isProcessingPayment = isProcessingPayment,
                    onPayNowClick = onPayNowClick
                )
            }
        }
    }
}

@Composable
private fun PendingPaymentCallout(
    isProcessingPayment: Boolean,
    onPayNowClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = Warning.copy(alpha = 0.12f),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(14.dp)
        ) {
            Row(
                verticalAlignment = Alignment.Top
            ) {
                Icon(
                    imageVector = Icons.Default.WarningAmber,
                    contentDescription = "Pending payment",
                    tint = Warning,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Payment pending",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold,
                        color = Gray700
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = "Complete your payment now to confirm this booking.",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                TextButton(onClick = onPayNowClick, enabled = !isProcessingPayment) {
                    Icon(
                        imageVector = Icons.Default.Payments,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(text = "Pay now")
                }

                if (isProcessingPayment) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = Primary
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Opening payment...",
                            style = MaterialTheme.typography.bodySmall,
                            color = Gray600
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun BookingStatusChip(status: String?) {
    val (backgroundColor, textColor) = when (status?.lowercase()) {
        "confirmed", "completed" -> Success.copy(alpha = 0.1f) to Success
        "pending" -> Warning.copy(alpha = 0.1f) to Warning
        "cancelled" -> Error.copy(alpha = 0.1f) to Error
        else -> Gray200 to Gray700
    }

    Surface(
        shape = RoundedCornerShape(8.dp),
        color = backgroundColor
    ) {
        Text(
            text = status?.replaceFirstChar { it.uppercase() } ?: "Unknown",
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Medium,
            color = textColor,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
        )
    }
}

@Composable
private fun PaymentStatusChip(status: String?) {
    val (backgroundColor, textColor, icon) = when (status?.lowercase()) {
        "paid", "settlement", "success" -> Triple(Success.copy(alpha = 0.1f), Success, Icons.Default.CheckCircle)
        "pending", "capture" -> Triple(Warning.copy(alpha = 0.1f), Warning, Icons.Default.Pending)
        "expired", "deny", "cancel", "failed" -> Triple(Error.copy(alpha = 0.1f), Error, Icons.Default.HourglassEmpty)
        else -> Triple(Gray200, Gray700, Icons.Default.HourglassEmpty)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .background(backgroundColor, RoundedCornerShape(8.dp))
            .padding(horizontal = 8.dp, vertical = 4.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = status,
            tint = textColor,
            modifier = Modifier.size(14.dp)
        )
        Spacer(modifier = Modifier.width(4.dp))
        Text(
            text = (status ?: "pending").replaceFirstChar { it.uppercase() },
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Medium,
            color = textColor
        )
    }
}


@Composable
private fun PaymentSuccessDialog(
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.CheckCircle,
                    contentDescription = "Success",
                    tint = Success,
                    modifier = Modifier.size(28.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = "Payment Successful!",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column {
                Text(
                    text = "Your payment has been processed successfully.",
                    style = MaterialTheme.typography.bodyMedium
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Your booking status is being updated.",
                    style = MaterialTheme.typography.bodySmall,
                    color = Gray600
                )
            }
        },
        confirmButton = {
            TextButton(
                onClick = onDismiss,
                modifier = Modifier
                    .background(Success, RoundedCornerShape(8.dp))
                    .padding(horizontal = 12.dp, vertical = 8.dp)
            ) {
                Text(
                    text = "Back to Bookings",
                    color = androidx.compose.ui.graphics.Color.White,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        containerColor = Surface,
        textContentColor = Gray700,
        shape = RoundedCornerShape(16.dp)
    )
}