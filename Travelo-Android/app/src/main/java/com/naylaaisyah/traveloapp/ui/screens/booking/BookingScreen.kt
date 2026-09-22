package com.naylaaisyah.traveloapp.ui.screens.booking

import android.app.Activity
import android.app.DatePickerDialog
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CardTravel
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

import com.naylaaisyah.traveloapp.ui.components.PaymentPendingDialog
import com.naylaaisyah.traveloapp.ui.components.PaymentSuccessDialog
import com.naylaaisyah.traveloapp.ui.components.PrimaryButton
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentWebViewActivity
import com.naylaaisyah.traveloapp.ui.theme.Background
import com.naylaaisyah.traveloapp.ui.theme.Error
import com.naylaaisyah.traveloapp.ui.theme.Gray300
import com.naylaaisyah.traveloapp.ui.theme.Gray400
import com.naylaaisyah.traveloapp.ui.theme.Gray500
import com.naylaaisyah.traveloapp.ui.theme.Gray600
import com.naylaaisyah.traveloapp.ui.theme.Gray700
import com.naylaaisyah.traveloapp.ui.theme.Primary
import com.naylaaisyah.traveloapp.ui.theme.PrimaryLight
import com.naylaaisyah.traveloapp.ui.theme.Surface
import com.naylaaisyah.traveloapp.util.MidtransHelper

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingScreen(
    tourPackageId: Int,
    tourPackageTitle: String,
    tourPackagePrice: Int,
    onBackClick: () -> Unit,
    onPaymentSuccess: (Int) -> Unit,
    viewModel: BookingViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current

    var selectedDate by remember { mutableStateOf("") }
    var peopleCount by remember { mutableIntStateOf(1) }
    val calendar = Calendar.getInstance()

    // Dialog states
    var showSuccessDialog by remember { mutableStateOf(false) }
    var showPendingDialog by remember { mutableStateOf(false) }
    var pendingBookingId by remember { mutableIntStateOf(0) }
    var shouldFetchUpdatedBooking by remember { mutableStateOf(false) }
    var bookingIdToFetch by remember { mutableIntStateOf(0) }

    val datePickerDialog = remember {
        DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                calendar.set(year, month, dayOfMonth)
                val dateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
                selectedDate = dateFormat.format(calendar.time)
            },
            calendar.get(Calendar.YEAR),
            calendar.get(Calendar.MONTH),
            calendar.get(Calendar.DAY_OF_MONTH)
        )
    }

    // Set minimum date to today
    datePickerDialog.datePicker.minDate = System.currentTimeMillis()

    // Activity result launcher for Midtrans payment
    // FIX: always verify with backend regardless of WebView result code.
    // Many Midtrans methods (VA/QRIS) never redirect to a success URL, so the
    // user simply presses back -> previously CANCELLED did resetState() with no
    // verification and the booking stayed pending forever.
    val paymentLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        android.util.Log.d("BookingScreen", "Payment activity result: ${result.resultCode}")

        // createdBooking may have been preserved now, but fall back to the
        // booking id captured at launch time (pendingBookingId).
        val bookingId = uiState.createdBooking?.id
            ?: pendingBookingId.takeIf { it > 0 }
            ?: bookingIdToFetch.takeIf { it > 0 }
            ?: 0

        when (result.resultCode) {
            PaymentWebViewActivity.RESULT_PAYMENT_SUCCESS,
            Activity.RESULT_OK -> {
                // Payment completed or user returned from payment page
                android.util.Log.d("BookingScreen", "Payment completed, will verify and sync...")

                // Verify and sync payment with Midtrans
                if (bookingId > 0) {
                    bookingIdToFetch = bookingId
                    pendingBookingId = bookingId
                    shouldFetchUpdatedBooking = true
                }
            }
            PaymentWebViewActivity.RESULT_PAYMENT_PENDING -> {
                android.util.Log.d("BookingScreen", "Payment pending, verifying with server...")
                if (bookingId > 0) {
                    pendingBookingId = bookingId
                    bookingIdToFetch = bookingId
                    shouldFetchUpdatedBooking = true
                } else {
                    pendingBookingId = 0
                    showPendingDialog = true
                }
            }
            PaymentWebViewActivity.RESULT_PAYMENT_CANCELLED,
            Activity.RESULT_CANCELED -> {
                android.util.Log.d("BookingScreen", "Payment cancelled/closed, verifying anyway...")
                if (bookingId > 0) {
                    bookingIdToFetch = bookingId
                    pendingBookingId = bookingId
                    shouldFetchUpdatedBooking = true
                } else {
                    viewModel.resetState()
                }
            }
            else -> {
                android.util.Log.d("BookingScreen", "Payment unknown result, verifying anyway...")
                if (bookingId > 0) {
                    bookingIdToFetch = bookingId
                    pendingBookingId = bookingId
                    shouldFetchUpdatedBooking = true
                } else {
                    viewModel.resetState()
                }
            }
        }
    }

    // Verify and sync payment with backend, with retries
    LaunchedEffect(shouldFetchUpdatedBooking) {
        if (shouldFetchUpdatedBooking && bookingIdToFetch > 0) {
            android.util.Log.d("BookingScreen", "Starting payment verification process...")
            
            // Wait briefly for Midtrans to update
            delay(2000)
            
            // Try to verify and sync payment up to 5 times
            var verifySuccess = false
            for (attempt in 0..4) {
                android.util.Log.d("BookingScreen", "Verify attempt ${attempt + 1}/5...")
                
                // Call the verify endpoint which syncs with Midtrans
                viewModel.verifyAndSyncPaymentStatus(bookingIdToFetch)
                
                delay(3000) // Wait for response
                
                // Check if payment is now marked as paid
                if (viewModel.uiState.value.paymentSuccess) {
                    android.util.Log.d("BookingScreen", "✓ Payment verified as PAID!")
                    verifySuccess = true
                    break
                }
                
                // If still pending after multiple attempts, last attempt uses retry endpoint
                if (attempt == 4 && !verifySuccess) {
                    android.util.Log.d("BookingScreen", "Final attempt: using retry endpoint...")
                    viewModel.retrySyncPaymentStatus(bookingIdToFetch)
                    delay(3000)
                    if (viewModel.uiState.value.paymentSuccess) {
                        android.util.Log.d("BookingScreen", "✓ Payment verified as PAID on retry!")
                        verifySuccess = true
                        break
                    }
                }
            }

            // FIX: if still not paid, show pending dialog so the user knows the
            // booking is waiting (previously the screen just went silent and the
            // booking looked stuck with no guidance).
            if (!verifySuccess && pendingBookingId > 0) {
                android.util.Log.d("BookingScreen", "Payment still pending after verification, showing pending dialog")
                showPendingDialog = true
            }

            shouldFetchUpdatedBooking = false
            bookingIdToFetch = 0
        }
    }

    // Launch payment when snap token is received
    LaunchedEffect(uiState.snapToken) {
        val snapToken = uiState.snapToken
        val booking = uiState.createdBooking

        if (snapToken != null && booking != null) {
            android.util.Log.d("BookingScreen", "Snap token received: $snapToken")
            android.util.Log.d("BookingScreen", "Launching payment for booking: ${booking.id}")

            // FIX: persist booking id BEFORE clearing state, otherwise the
            // payment-result handler loses the id and never verifies.
            pendingBookingId = booking.id
            bookingIdToFetch = booking.id

            // Launch Midtrans payment WebView via activity result launcher
            val intent = android.content.Intent(context, PaymentWebViewActivity::class.java).apply {
                putExtra(PaymentWebViewActivity.EXTRA_SNAP_TOKEN, snapToken)
                putExtra(PaymentWebViewActivity.EXTRA_IS_SANDBOX, true)
            }
            paymentLauncher.launch(intent)

            // Clear only the token; keep createdBooking so result handler can verify.
            viewModel.consumeSnapToken()
        }
    }

    // Fetch updated booking when payment success is detected
    LaunchedEffect(uiState.paymentSuccess) {
        if (uiState.paymentSuccess && uiState.updatedBooking != null) {
            android.util.Log.d("BookingScreen", "Payment success confirmed!")
            android.util.Log.d("BookingScreen", "Payment status: ${uiState.updatedBooking?.payment?.status}")
            android.util.Log.d("BookingScreen", "Booking status: ${uiState.updatedBooking?.status}")
            
            pendingBookingId = uiState.updatedBooking!!.id
            showSuccessDialog = true
        }
    }

    // Show dialogs
    if (showSuccessDialog) {
        PaymentSuccessDialog(
            bookingId = pendingBookingId,
            onDismiss = {
                showSuccessDialog = false
                viewModel.resetState()
                onPaymentSuccess(pendingBookingId)
            },
            onViewBooking = {
                showSuccessDialog = false
                viewModel.resetState()
                onPaymentSuccess(pendingBookingId)
            }
        )
    }

    if (showPendingDialog) {
        PaymentPendingDialog(
            bookingId = pendingBookingId,
            onDismiss = {
                showPendingDialog = false
                viewModel.resetState()
                onPaymentSuccess(pendingBookingId)
            },
            onViewBooking = {
                showPendingDialog = false
                viewModel.resetState()
                onPaymentSuccess(pendingBookingId)
            }
        )
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Book Tour",
                onBackClick = onBackClick
            )
        },
        containerColor = Background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(16.dp)
        ) {
            // Tour Package Info Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = Surface)
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.CardTravel,
                        contentDescription = "Tour",
                        tint = Primary,
                        modifier = Modifier.size(48.dp)
                    )
                    Spacer(modifier = Modifier.width(16.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = tourPackageTitle,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Rp ${String.format("%,d", tourPackagePrice)} per person",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Gray600
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Booking Date
            Text(
                text = "Select Date",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))

            OutlinedTextField(
                value = selectedDate,
                onValueChange = { },
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("Choose your travel date") },
                leadingIcon = {
                    Icon(
                        imageVector = Icons.Default.CalendarMonth,
                        contentDescription = "Date"
                    )
                },
                trailingIcon = {
                    IconButton(onClick = { datePickerDialog.show() }) {
                        Icon(
                            imageVector = Icons.Default.DateRange,
                            contentDescription = "Select Date"
                        )
                    }
                },
                readOnly = true,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Primary,
                    unfocusedBorderColor = Gray300
                )
            )

            Spacer(modifier = Modifier.height(24.dp))

            // Number of People
            Text(
                text = "Number of People",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = Surface)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.People,
                        contentDescription = "People",
                        tint = Primary
                    )
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        IconButton(
                            onClick = { if (peopleCount > 1) peopleCount-- }
                        ) {
                            Icon(
                                imageVector = Icons.Default.Remove,
                                contentDescription = "Decrease",
                                tint = if (peopleCount > 1) Primary else Gray400
                            )
                        }
                        Text(
                            text = "$peopleCount",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.width(40.dp),
                            textAlign = TextAlign.Center
                        )
                        IconButton(
                            onClick = { if (peopleCount < 10) peopleCount++ }
                        ) {
                            Icon(
                                imageVector = Icons.Default.Add,
                                contentDescription = "Increase",
                                tint = if (peopleCount < 10) Primary else Gray400
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Price Summary
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = PrimaryLight.copy(alpha = 0.1f))
            ) {
                Column(
                    modifier = Modifier.padding(16.dp)
                ) {
                    Text(
                        text = "Price Summary",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(12.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Rp ${String.format("%,d", tourPackagePrice)} x $peopleCount people",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Gray700
                        )
                        Text(
                            text = "Rp ${String.format("%,d", tourPackagePrice * peopleCount)}",
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Medium
                        )
                    }

                    Divider(modifier = Modifier.padding(vertical = 8.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Total",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Rp ${String.format("%,d", tourPackagePrice * peopleCount)}",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = Primary
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Error Message
            if (uiState.error != null) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = com.naylaaisyah.traveloapp.ui.theme.Error.copy(alpha = 0.1f)),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.Error,
                            contentDescription = null,
                            tint = com.naylaaisyah.traveloapp.ui.theme.Error,
                            modifier = Modifier.size(24.dp)
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(
                            text = uiState.error!!,
                            style = MaterialTheme.typography.bodyMedium,
                            color = com.naylaaisyah.traveloapp.ui.theme.Error
                        )
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))
            }

            // Book Now Button
            PrimaryButton(
                text = "Continue to Payment",
                onClick = {
                    android.util.Log.d("BookingScreen", "Button clicked!")
                    android.util.Log.d("BookingScreen", "selectedDate: $selectedDate")
                    android.util.Log.d("BookingScreen", "peopleCount: $peopleCount")
                    android.util.Log.d("BookingScreen", "enabled: ${selectedDate.isNotEmpty() && peopleCount > 0 && !uiState.isLoading}")
                    
                    viewModel.createBooking(
                        tourPackageId = tourPackageId,
                        bookingDate = selectedDate,
                        peopleCount = peopleCount,
                        totalPrice = tourPackagePrice * peopleCount
                    )
                },
                isLoading = uiState.isLoading,
                enabled = selectedDate.isNotEmpty() && peopleCount > 0 && !uiState.isLoading
            )

            // Info text
            if (!uiState.isLoading && uiState.error == null) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "🔒 Secure payment via Midtrans",
                    style = MaterialTheme.typography.bodySmall,
                    color = Gray500,
                    modifier = Modifier.fillMaxWidth(),
                    textAlign = TextAlign.Center
                )
            }
        }
    }
}

