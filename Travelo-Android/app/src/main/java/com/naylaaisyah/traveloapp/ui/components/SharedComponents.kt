package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.IconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.ExperimentalMaterial3Api
import com.naylaaisyah.traveloapp.ui.theme.Error
import com.naylaaisyah.traveloapp.ui.theme.Primary
import com.naylaaisyah.traveloapp.ui.theme.Warning

@Composable
fun LoadingIndicator(modifier: Modifier = Modifier) {
    Box(modifier = modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = Primary)
    }
}

@Composable
fun ErrorMessage(
    message: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.fillMaxWidth().padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(text = message, style = MaterialTheme.typography.bodyMedium, color = Error)
        Button(onClick = onRetry) { Text("Retry") }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TraveloTopBar(
    title: String,
    onBackClick: () -> Unit,
    actions: @Composable RowScope.() -> Unit = {}
) {
    TopAppBar(
        title = { Text(text = title) },
        navigationIcon = {
            IconButton(onClick = onBackClick) {
                Icon(imageVector = Icons.Filled.ArrowBack, contentDescription = "Back")
            }
        },
        actions = actions,
        colors = TopAppBarDefaults.topAppBarColors(containerColor = com.naylaaisyah.traveloapp.ui.theme.Surface)
    )
}

@Composable
fun PrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    isLoading: Boolean = false
) {
    Button(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        enabled = enabled && !isLoading,
        colors = ButtonDefaults.buttonColors(containerColor = Primary)
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    color = Color.White,
                    strokeWidth = 2.dp
                )
            Spacer(modifier = Modifier.width(8.dp))
        }
        Text(text)
    }
}

@Composable
fun PaymentPendingDialog(
    bookingId: Int,
    onDismiss: () -> Unit,
    onViewBooking: () -> Unit,
    modifier: Modifier = Modifier
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            Button(onClick = onViewBooking) { Text("View Booking") }
        },
        dismissButton = {
            Button(onClick = onDismiss) { Text("Cancel") }
        },
        icon = {
            Icon(imageVector = Icons.Filled.Warning, contentDescription = null, tint = Warning)
        },
        title = { Text("Payment Pending") },
        text = { Text("Your payment is pending. Booking ID: $bookingId") }
    )
}

@Composable
fun PaymentSuccessDialog(
    bookingId: Int,
    onDismiss: () -> Unit,
    onViewBooking: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            Button(onClick = onViewBooking) { Text("View Booking") }
        },
        dismissButton = {
            Button(onClick = onDismiss) { Text("OK") }
        },
        icon = {
            Icon(imageVector = Icons.Filled.CheckCircle, contentDescription = null, tint = Warning)
        },
        title = { Text("Payment Success") },
        text = { Text("Your payment has been processed successfully. Booking ID: $bookingId") }
    )
}