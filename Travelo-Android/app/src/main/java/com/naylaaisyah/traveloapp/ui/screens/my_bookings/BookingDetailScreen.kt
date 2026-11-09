package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Download
import androidx.compose.material3.Divider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.BookingChangeRequest
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.paymentOutcome
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * Booking detail reached from a notification. The `e_ticket` deep link uses the
 * same screen with `openTicketOnLoad`, so the ticket dialog is backed by the
 * same backend verification as My Bookings.
 */
@Composable
fun BookingDetailScreen(
    bookingId: Int,
    openTicketOnLoad: Boolean = false,
    onBackClick: () -> Unit,
    onOpenMyBookings: (String) -> Unit,
    viewModel: BookingDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    val context = LocalContext.current

    LaunchedEffect(bookingId, openTicketOnLoad) {
        viewModel.load(bookingId, openTicketOnLoad)
    }

    LaunchedEffect(uiState.redirectNotice) {
        val notice = uiState.redirectNotice ?: return@LaunchedEffect
        viewModel.consumeRedirectNotice()
        onOpenMyBookings(notice)
    }

    LaunchedEffect(uiState.ticketError) {
        val message = uiState.ticketError ?: return@LaunchedEffect
        snackbarHostState.showSnackbar(message)
        viewModel.consumeTicketError()
    }

    LaunchedEffect(uiState.invoiceError) {
        val message = uiState.invoiceError ?: return@LaunchedEffect
        snackbarHostState.showSnackbar(message)
        viewModel.consumeInvoiceError()
    }

    LaunchedEffect(uiState.invoiceFile) {
        val invoice = uiState.invoiceFile ?: return@LaunchedEffect
        viewModel.consumeInvoiceFile()
        openInvoice(context, invoice)?.let { message ->
            snackbarHostState.showSnackbar(message)
        }
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Booking detail",
                onBackClick = onBackClick
            )
        },
        snackbarHost = { SnackbarHost(snackbarHostState) },
        containerColor = VanillaCream
    ) { paddingValues ->
        when {
            uiState.isLoading && uiState.booking == null -> {
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
                    message = uiState.error ?: "We couldn't load this booking.",
                    onRetry = viewModel::retry,
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = "Couldn't load this booking",
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            else -> {
                BookingDetailContent(
                    booking = uiState.booking!!,
                    invoiceDownloading = uiState.invoiceDownloading,
                    onViewTicket = { viewModel.loadTicket() },
                    onDownloadInvoice = { viewModel.downloadInvoice() },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                )
            }
        }
    }

    if (uiState.ticketLoadingFor != null) {
        ETicketLoadingDialog(onDismiss = {})
    }

    uiState.ticket?.let { ticket ->
        uiState.booking?.let { booking ->
            ETicketDialog(
                booking = booking,
                ticket = ticket,
                onDismiss = viewModel::clearTicket
            )
        }
    }
}

@Composable
private fun BookingDetailContent(
    booking: Booking,
    invoiceDownloading: Boolean,
    onViewTicket: () -> Unit,
    onDownloadInvoice: () -> Unit,
    modifier: Modifier = Modifier
) {
    val outcome = booking.paymentOutcome()
    val isCancelled = booking.status.equals("cancelled", ignoreCase = true)
    val canShowTicket = outcome == PaymentOutcome.SUCCESS && !isCancelled
    val canDownloadInvoice = outcome == PaymentOutcome.SUCCESS

    LazyColumn(
        modifier = modifier,
        contentPadding = PaddingValues(
            start = TraveloSpacing.screen,
            end = TraveloSpacing.screen,
            top = TraveloSpacing.sm,
            bottom = TraveloSpacing.xl
        ),
        verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
    ) {
        item {
            TraveloCard {
                Text(
                    text = "Booking #${booking.id}",
                    style = MaterialTheme.typography.labelMedium,
                    color = PlumMuted
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = booking.tourPackage?.safeTitle ?: "Tour package",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )

                Spacer(Modifier.height(TraveloSpacing.sm))
                Divider(color = PlumBorder)
                Spacer(Modifier.height(TraveloSpacing.sm))

                DetailRow(
                    label = "Travel date",
                    value = formatDisplayDate(booking.bookingDate)
                )
                DetailRow(
                    label = "Travelers",
                    value = if (booking.peopleCount == 1) {
                        "1 traveler"
                    } else {
                        "${booking.peopleCount} travelers"
                    }
                )
                DetailRow(label = "Payment", value = outcome.label())
                DetailRow(
                    label = "Booking status",
                    value = booking.status.replaceFirstChar { it.uppercase() }
                )
                DetailRow(
                    label = "Total",
                    value = formatRupiah(booking.totalPrice),
                    emphasize = true
                )
            }
        }

        if (canShowTicket) {
            item {
                TraveloButton(
                    text = stringResource(R.string.eticket_view),
                    onClick = onViewTicket,
                    variant = TraveloButtonVariant.Secondary,
                    size = TraveloButtonSize.Medium,
                    leadingIcon = Icons.Default.ConfirmationNumber
                )
            }
        }

        if (canDownloadInvoice) {
            item {
                TraveloButton(
                    text = stringResource(R.string.invoice_download),
                    onClick = onDownloadInvoice,
                    variant = TraveloButtonVariant.Secondary,
                    size = TraveloButtonSize.Medium,
                    enabled = !invoiceDownloading,
                    loading = invoiceDownloading,
                    leadingIcon = Icons.Default.Download
                )
            }
        }

        booking.latestChangeRequest
            ?.takeIf { it.status == BookingChangeRequest.STATUS_APPROVED }
            ?.let { request ->
                item {
                    TraveloCard {
                        Text(
                            text = "Cancellation refund",
                            style = MaterialTheme.typography.titleSmall,
                            color = PlumInk
                        )
                        Spacer(Modifier.height(TraveloSpacing.xs))
                        DetailRow(
                            label = "Refund status",
                            value = request.refundStatusLabel()
                        )
                        request.refundPercentage?.let { percentage ->
                            DetailRow(
                                label = "Refund percentage",
                                value = "$percentage%"
                            )
                        }
                        request.refundAmount?.let { amount ->
                            DetailRow(
                                label = "Refund amount",
                                value = formatRupiah(amount),
                                emphasize = true
                            )
                        }
                        request.refundFailureReason
                            ?.takeIf { it.isNotBlank() }
                            ?.let { reason ->
                                Spacer(Modifier.height(TraveloSpacing.xs))
                                Text(
                                    text = reason,
                                    style = MaterialTheme.typography.bodySmall,
                                    color = Rosewood
                                )
                            }
                    }
                }
            }

        item {
            Text(
                text = "Opened from a Travelo notification. Pull to refresh in " +
                    "My Bookings if the status has just changed.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted,
                modifier = Modifier.padding(top = TraveloSpacing.xs)
            )
        }
    }
}

@Composable
private fun DetailRow(
    label: String,
    value: String,
    emphasize: Boolean = false
) {
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
        Text(
            text = value,
            style = if (emphasize) {
                MaterialTheme.typography.titleSmall
            } else {
                MaterialTheme.typography.bodyMedium
            },
            color = if (emphasize) Rosewood else PlumInk
        )
    }
}

private fun PaymentOutcome.label(): String = when (this) {
    PaymentOutcome.SUCCESS -> "Paid"
    PaymentOutcome.WAITING -> "Pending"
    PaymentOutcome.FAILED -> "Failed"
    PaymentOutcome.EXPIRED -> "Expired"
    PaymentOutcome.CANCELLED -> "Cancelled"
}
