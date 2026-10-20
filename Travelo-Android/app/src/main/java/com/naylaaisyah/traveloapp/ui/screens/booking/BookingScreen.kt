package com.naylaaisyah.traveloapp.ui.screens.booking

import android.app.DatePickerDialog
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloTextField
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.FieldShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

/**
 * Step 1 of the flow: the booking form only.
 *
 * The request payload is unchanged (tour_package_id, people_count,
 * booking_date, special_requests). The traveller cap now comes from the
 * selected tour package instead of a hardcoded 10.
 */
@Composable
fun BookingScreen(
    tourPackageId: Int,
    tourPackageTitle: String,
    tourPackagePrice: Int,
    tourPackageMaxPeople: Int = 10,
    onBackClick: () -> Unit,
    onPaymentSuccess: (Int) -> Unit,
    onContinueToPayment: (Int) -> Unit = onPaymentSuccess,
    viewModel: BookingViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current

    var selectedDate by remember { mutableStateOf("") }
    var peopleCount by remember { mutableIntStateOf(1) }
    var specialRequests by remember { mutableStateOf("") }

    val maxPeople = if (tourPackageMaxPeople > 0) tourPackageMaxPeople else 10
    val totalPrice = tourPackagePrice.toLong() * peopleCount

    val datePickerDialog = remember {
        val calendar = Calendar.getInstance()
        DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                val chosen = Calendar.getInstance().apply {
                    set(year, month, dayOfMonth)
                }
                selectedDate = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(chosen.time)
            },
            calendar.get(Calendar.YEAR),
            calendar.get(Calendar.MONTH),
            calendar.get(Calendar.DAY_OF_MONTH)
        ).apply {
            // Minus a second so "today" stays selectable on devices that round
            // the minimum date to the start of the next day.
            datePicker.minDate = System.currentTimeMillis() - 1_000
        }
    }

    LaunchedEffect(uiState.bookingSuccess, uiState.createdBooking) {
        val booking = uiState.createdBooking
        if (uiState.bookingSuccess && booking != null) {
            viewModel.resetState()
            onContinueToPayment(booking.id)
        }
    }

    Scaffold(containerColor = VanillaCream) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.xs)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xxs)
            ) {
                TraveloIconButton(
                    icon = Icons.Default.ArrowBack,
                    contentDescription = "Back",
                    onClick = onBackClick
                )
                Text(
                    text = "Book tour",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloCard {
                Text(
                    text = tourPackageTitle,
                    style = MaterialTheme.typography.titleMedium,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xxs))
                TraveloPriceText(
                    amount = tourPackagePrice.toDouble(),
                    compact = true,
                    suffix = "/person"
                )
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Text(
                text = "Travel date",
                style = MaterialTheme.typography.titleMedium,
                color = PlumInk
            )
            Spacer(Modifier.height(TraveloSpacing.xs))

            Surface(
                onClick = { datePickerDialog.show() },
                modifier = Modifier.fillMaxWidth(),
                shape = FieldShape,
                color = SurfaceWhite,
                border = BorderStroke(1.dp, PlumBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 56.dp)
                        .padding(horizontal = TraveloSpacing.md),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.CalendarMonth,
                        contentDescription = null,
                        tint = Rosewood,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(Modifier.width(TraveloSpacing.sm))
                    Text(
                        text = if (selectedDate.isBlank()) {
                            "Choose your travel date"
                        } else {
                            formatDisplayDate(selectedDate)
                        },
                        style = MaterialTheme.typography.bodyLarge,
                        color = if (selectedDate.isBlank()) PlumMuted else PlumInk,
                        modifier = Modifier.weight(1f)
                    )
                    Icon(
                        imageVector = Icons.Default.DateRange,
                        contentDescription = null,
                        tint = PlumMuted,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Travelers",
                    style = MaterialTheme.typography.titleMedium,
                    color = PlumInk,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    text = "Up to $maxPeople",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
            Spacer(Modifier.height(TraveloSpacing.xs))

            Surface(
                shape = CardShape,
                color = SurfaceWhite,
                border = BorderStroke(1.dp, PlumBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = TraveloSpacing.xs, vertical = TraveloSpacing.xxs),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = if (peopleCount == 1) "1 traveler" else "$peopleCount travelers",
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumInk,
                        modifier = Modifier
                            .weight(1f)
                            .padding(start = TraveloSpacing.xs)
                    )
                    IconButton(
                        onClick = { if (peopleCount > 1) peopleCount-- },
                        enabled = peopleCount > 1
                    ) {
                        Icon(
                            imageVector = Icons.Default.Remove,
                            contentDescription = "Remove one traveler",
                            tint = if (peopleCount > 1) Rosewood else PlumMuted.copy(alpha = 0.4f)
                        )
                    }
                    Text(
                        text = "$peopleCount",
                        style = MaterialTheme.typography.titleMedium,
                        color = PlumInk,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.width(32.dp)
                    )
                    IconButton(
                        onClick = { if (peopleCount < maxPeople) peopleCount++ },
                        enabled = peopleCount < maxPeople
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = "Add one traveler",
                            tint = if (peopleCount < maxPeople) Rosewood else PlumMuted.copy(alpha = 0.4f)
                        )
                    }
                }
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloTextField(
                value = specialRequests,
                onValueChange = { specialRequests = it },
                label = "Special requests (optional)",
                placeholder = "Meal, room, or accessibility notes",
                singleLine = false
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            Surface(shape = CardShape, color = BlushMist) {
                Column(modifier = Modifier.padding(TraveloSpacing.md)) {
                    Text(
                        text = "Price summary",
                        style = MaterialTheme.typography.titleMedium,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(TraveloSpacing.sm))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "${formatRupiah(tourPackagePrice)} × $peopleCount",
                            style = MaterialTheme.typography.bodyMedium,
                            color = PlumMuted
                        )
                        Text(
                            text = formatRupiah(totalPrice.toDouble()),
                            style = MaterialTheme.typography.bodyMedium,
                            color = PlumInk
                        )
                    }

                    Spacer(
                        Modifier
                            .padding(vertical = TraveloSpacing.sm)
                            .fillMaxWidth()
                            .height(1.dp)
                            .background(PlumBorder)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Total",
                            style = MaterialTheme.typography.titleMedium,
                            color = PlumInk
                        )
                        TraveloPriceText(
                            amount = totalPrice.toDouble(),
                            emphasized = true
                        )
                    }
                }
            }

            if (uiState.error != null) {
                Spacer(Modifier.height(TraveloSpacing.md))
                Surface(
                    shape = CardShape,
                    color = DangerTint,
                    border = BorderStroke(1.dp, DangerDeep.copy(alpha = 0.15f))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(TraveloSpacing.sm),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.Error,
                            contentDescription = null,
                            tint = DangerDeep,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(Modifier.width(TraveloSpacing.xs))
                        Text(
                            text = uiState.error.orEmpty(),
                            style = MaterialTheme.typography.bodySmall,
                            color = DangerDeep
                        )
                    }
                }
            }

            Spacer(Modifier.height(TraveloSpacing.md))

            TraveloButton(
                text = "Continue to payment",
                onClick = {
                    viewModel.createBooking(
                        tourPackageId = tourPackageId,
                        bookingDate = selectedDate,
                        peopleCount = peopleCount,
                        specialRequests = specialRequests
                    )
                },
                loading = uiState.isLoading,
                enabled = selectedDate.isNotEmpty() && peopleCount in 1..maxPeople
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

            Spacer(Modifier.height(TraveloSpacing.lg))
        }
    }
}
