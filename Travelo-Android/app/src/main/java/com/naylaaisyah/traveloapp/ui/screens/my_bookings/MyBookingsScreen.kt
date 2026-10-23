package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.Surface
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import kotlinx.coroutines.delay
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.paymentOutcome
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryJob
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryRules
import com.naylaaisyah.traveloapp.data.tickets.TicketHistoryRules
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
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
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.formatDisplayDate

/** Gate scans can happen while this screen stays open. */
private const val BOOKINGS_POLL_INTERVAL_MS = 10_000L

/**
 * Booking list. Payment status always comes from the backend (kept current by
 * the Midtrans webhook + verification on the payment screens). The list
 * refreshes whenever it becomes visible, so a booking paid elsewhere turns
 * Paid here without a manual step.
 *
 * Pay actions navigate to the dedicated Payment screen — checking status is
 * not a normal step for the user.
 */
@OptIn(ExperimentalMaterialApi::class)
@Composable
fun MyBookingsScreen(
    onBackClick: () -> Unit,
    onPayBooking: (Int) -> Unit = {},
    onCompleteDocuments: (Int) -> Unit = {},
    onTicketHistoryClick: () -> Unit = {},
    viewModel: MyBookingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    val isRefreshing = uiState.isLoading && uiState.bookings.isNotEmpty()

    // Used/expired tickets are no longer active bookings: they move to Ticket
    // history and their e-ticket action disappears from this list.
    val activeBookings = remember(uiState.bookings) {
        TicketHistoryRules.current(uiState.bookings)
    }

    // Jobs not present in the current server list still need an action. This
    // is the offline/restart case; once the list loads they merge into cards.
    val orphanJobs = uiState.pendingRecovery.filter { job ->
        uiState.bookings.none { it.id == job.bookingId }
    }
    val pullRefreshState = rememberPullRefreshState(
        refreshing = isRefreshing,
        onRefresh = { viewModel.loadBookings() }
    )

    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            viewModel.loadBookings()

            // A ticket can be scanned at the gate while this screen is open.
            // Poll silently so the backend's `used` state moves the booking to
            // history and raises the scanned-ticket notice without a manual
            // refresh. The loop stops as soon as the screen is not RESUMED.
            while (true) {
                delay(BOOKINGS_POLL_INTERVAL_MS)
                viewModel.loadBookings(silent = true)
            }
        }
    }

    LaunchedEffect(uiState.error) {
        uiState.error?.let { message ->
            if (uiState.bookings.isNotEmpty() || orphanJobs.isNotEmpty()) {
                snackbarHostState.showSnackbar(message)
                viewModel.clearError()
            }
        }
    }

    val ticketErrorMessage = uiState.ticketError
        ?: uiState.ticketErrorRes?.let { stringResource(it) }

    LaunchedEffect(ticketErrorMessage) {
        ticketErrorMessage?.let { message ->
            snackbarHostState.showSnackbar(message)
            viewModel.clearTicketError()
        }
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "My bookings",
                onBackClick = onBackClick,
                actions = {
                    IconButton(onClick = onTicketHistoryClick) {
                        Icon(
                            imageVector = Icons.Default.History,
                            contentDescription = stringResource(R.string.ticket_history_open),
                            tint = Rosewood
                        )
                    }
                    IconButton(onClick = { viewModel.loadBookings() }) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Refresh bookings",
                            tint = Rosewood
                        )
                    }
                }
            )
        },
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        containerColor = VanillaCream
    ) { paddingValues ->
        when {
            uiState.isLoading && uiState.bookings.isEmpty() && orphanJobs.isEmpty() -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .padding(horizontal = TraveloSpacing.screen)
                ) {
                    TraveloLoadingState()
                }
            }

            uiState.error != null && uiState.bookings.isEmpty() && orphanJobs.isEmpty() -> {
                TraveloErrorState(
                    message = uiState.error.orEmpty(),
                    onRetry = { viewModel.loadBookings() },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = "Couldn't load your bookings",
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            uiState.bookings.isEmpty() && orphanJobs.isEmpty() -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentAlignment = Alignment.Center
                ) {
                    TraveloEmptyState(
                        title = "No bookings yet",
                        message = "Your bookings will appear here after you book a tour package.",
                        icon = Icons.Default.ReceiptLong,
                        modifier = Modifier.padding(horizontal = TraveloSpacing.screen)
                    )
                }
            }

            activeBookings.isEmpty() && orphanJobs.isEmpty() -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentAlignment = Alignment.Center
                ) {
                    TraveloEmptyState(
                        title = stringResource(R.string.ticket_history_no_active_title),
                        message = stringResource(R.string.ticket_history_no_active_message),
                        icon = Icons.Default.History,
                        actionLabel = stringResource(R.string.ticket_history_view),
                        onAction = onTicketHistoryClick,
                        modifier = Modifier.padding(horizontal = TraveloSpacing.screen)
                    )
                }
            }

            else -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .pullRefresh(pullRefreshState)
                ) {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(
                            horizontal = TraveloSpacing.screen,
                            vertical = TraveloSpacing.sm
                        ),
                        verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
                    ) {
                        if (orphanJobs.isNotEmpty()) {
                            item(key = "recovery-header") {
                                Text(
                                    text = "Needs attention",
                                    style = MaterialTheme.typography.titleSmall,
                                    color = PlumInk,
                                    modifier = Modifier.padding(top = TraveloSpacing.xs)
                                )
                            }
                            items(orphanJobs, key = { "recovery-${it.bookingId}" }) { job ->
                                RecoveryJobCard(
                                    job = job,
                                    onCompleteDocuments = {
                                        onCompleteDocuments(job.bookingId)
                                    }
                                )
                            }
                        }

                        items(activeBookings, key = { it.id }) { booking ->
                            BookingCard(
                                booking = booking,
                                onPayNowClick = { onPayBooking(booking.id) },
                                onViewTicket = { viewModel.loadTicket(booking.id) },
                                onCompleteDocuments = {
                                    onCompleteDocuments(booking.id)
                                }
                            )
                        }
                    }

                    PullRefreshIndicator(
                        refreshing = isRefreshing,
                        state = pullRefreshState,
                        modifier = Modifier.align(Alignment.TopCenter),
                        backgroundColor = SurfaceWhite,
                        contentColor = Rosewood
                    )
                }
            }
        }
    }

    uiState.ticketLoadingFor?.let {
        // The request is short; keep the dialog non-dismissible so a late
        // response cannot reopen a ticket after the user closed the screen.
        ETicketLoadingDialog(onDismiss = {})
    }

    uiState.ticket?.let { ticket ->
        uiState.bookings.firstOrNull { it.id == ticket.bookingId }?.let { booking ->
            ETicketDialog(
                booking = booking,
                ticket = ticket,
                onDismiss = viewModel::clearTicket
            )
        }
    }

    uiState.scannedTicketNotice?.let { booking ->
        TicketScannedDialog(
            booking = booking,
            onViewHistory = {
                viewModel.dismissScannedTicketNotice()
                onTicketHistoryClick()
            },
            onDismiss = viewModel::dismissScannedTicketNotice
        )
    }
}

@Composable
private fun BookingCard(
    booking: Booking,
    onPayNowClick: () -> Unit,
    onViewTicket: () -> Unit,
    onCompleteDocuments: () -> Unit
) {
    val outcome = booking.paymentOutcome()
    val needsPayment = outcome == PaymentOutcome.WAITING ||
        outcome == PaymentOutcome.FAILED ||
        outcome == PaymentOutcome.EXPIRED ||
        outcome == PaymentOutcome.CANCELLED
    // Backend gate: an e-ticket stays locked until every visitor document is
    // on file. Pending/verified documents count as on file; missing/rejected/
    // purged rows need another upload.
    val needsDocuments = DocumentRecoveryRules.needsIdentityDocuments(booking)

    TraveloCard {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xs),
            verticalAlignment = Alignment.Top
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Booking #${booking.id}",
                    style = MaterialTheme.typography.labelMedium,
                    color = PlumMuted
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = booking.tourPackage?.safeTitle ?: "Tour package",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
            PaymentStatusChip(outcome = outcome)
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.md),
            verticalAlignment = Alignment.CenterVertically
        ) {
            BookingMeta(
                icon = Icons.Default.CalendarMonth,
                text = formatDisplayDate(booking.bookingDate)
            )
            BookingMeta(
                icon = Icons.Default.People,
                text = if (booking.peopleCount == 1) {
                    "1 traveler"
                } else {
                    "${booking.peopleCount} travelers"
                }
            )
        }

        Divider(
            modifier = Modifier.padding(vertical = TraveloSpacing.sm),
            color = PlumBorder
        )

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Bottom
        ) {
            Text(
                text = "Total",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
            TraveloPriceText(
                amount = booking.totalPrice,
                compact = true
            )
        }

        if (
            outcome == PaymentOutcome.SUCCESS &&
            !needsDocuments &&
            !TicketHistoryRules.isHistoryTicket(booking)
        ) {
            Spacer(Modifier.height(TraveloSpacing.sm))
            TraveloButton(
                text = stringResource(R.string.eticket_view),
                onClick = onViewTicket,
                variant = TraveloButtonVariant.Secondary,
                size = TraveloButtonSize.Medium,
                leadingIcon = Icons.Default.ConfirmationNumber
            )
        }

        if (needsDocuments) {
            Spacer(Modifier.height(TraveloSpacing.sm))
            DocumentActionCallout(onCompleteDocuments = onCompleteDocuments)
        }

        if (needsPayment) {
            Spacer(Modifier.height(TraveloSpacing.sm))
            PaymentActionCallout(
                outcome = outcome,
                onPayNowClick = onPayNowClick
            )
        }
    }
}

/**
 * Local mutation holds the booking id and title only, so a booking that was
 * interrupted before upload is still discoverable after restart or offline.
 */
@Composable
private fun RecoveryJobCard(
    job: DocumentRecoveryJob,
    onCompleteDocuments: () -> Unit
) {
    TraveloCard {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Top
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Booking #${job.bookingId}",
                    style = MaterialTheme.typography.labelMedium,
                    color = PlumMuted
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = job.tourTitle.ifBlank { "Tour package" },
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
            Surface(shape = PillShape, color = WarningTint) {
                Text(
                    text = "Documents",
                    style = MaterialTheme.typography.labelSmall,
                    color = WarningDeep,
                    modifier = Modifier.padding(horizontal = TraveloSpacing.xs, vertical = 4.dp)
                )
            }
        }

        Spacer(Modifier.height(TraveloSpacing.xs))

        Text(
            text = stringResource(R.string.documents_need_attention),
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )

        Spacer(Modifier.height(TraveloSpacing.sm))

        TraveloButton(
            text = stringResource(R.string.documents_action),
            onClick = onCompleteDocuments,
            variant = TraveloButtonVariant.Secondary,
            size = TraveloButtonSize.Medium,
            leadingIcon = Icons.Default.Description
        )
    }
}

@Composable
private fun DocumentActionCallout(onCompleteDocuments: () -> Unit) {
    androidx.compose.material3.Surface(
        shape = MaterialTheme.shapes.medium,
        color = WarningTint,
        border = BorderStroke(1.dp, WarningDeep.copy(alpha = 0.15f)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(TraveloSpacing.sm)) {
            Row(verticalAlignment = Alignment.Top) {
                Icon(
                    imageVector = Icons.Default.Description,
                    contentDescription = null,
                    tint = WarningDeep,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(Modifier.width(TraveloSpacing.xs))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Identity documents needed",
                        style = MaterialTheme.typography.titleSmall,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = stringResource(R.string.documents_need_attention) +
                            " Existing documents are kept; only missing ones are uploaded.",
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted
                    )
                }
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloButton(
                text = stringResource(R.string.documents_action),
                onClick = onCompleteDocuments,
                variant = TraveloButtonVariant.Secondary,
                size = TraveloButtonSize.Medium,
                leadingIcon = Icons.Default.Description
            )
        }
    }
}

@Composable
private fun BookingMeta(icon: ImageVector, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = PlumMuted,
            modifier = Modifier.size(16.dp)
        )
        Spacer(Modifier.width(4.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted
        )
    }
}

@Composable
private fun PaymentActionCallout(
    outcome: PaymentOutcome,
    onPayNowClick: () -> Unit
) {
    val (title, subtitle, buttonText) = when (outcome) {
        PaymentOutcome.WAITING -> Triple(
            "Payment pending",
            "Complete your payment to confirm this booking.",
            "Pay now"
        )

        PaymentOutcome.EXPIRED -> Triple(
            "Payment expired",
            "Start a new payment to confirm this booking.",
            "Pay again"
        )

        PaymentOutcome.CANCELLED -> Triple(
            "Payment cancelled",
            "Start a new payment to confirm this booking.",
            "Pay again"
        )

        else -> Triple(
            "Payment failed",
            "Try again to confirm this booking.",
            "Try again"
        )
    }

    val accent = if (outcome == PaymentOutcome.FAILED || outcome == PaymentOutcome.CANCELLED) {
        DangerDeep
    } else {
        WarningDeep
    }
    val background = if (outcome == PaymentOutcome.FAILED || outcome == PaymentOutcome.CANCELLED) {
        DangerTint
    } else {
        WarningTint
    }
    val icon = if (outcome == PaymentOutcome.EXPIRED) {
        Icons.Default.Schedule
    } else {
        Icons.Default.ErrorOutline
    }

    androidx.compose.material3.Surface(
        shape = MaterialTheme.shapes.medium,
        color = background,
        border = BorderStroke(1.dp, accent.copy(alpha = 0.15f)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(TraveloSpacing.sm)) {
            Row(verticalAlignment = Alignment.Top) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = accent,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(Modifier.width(TraveloSpacing.xs))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleSmall,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = subtitle,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted
                    )
                }
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloButton(
                text = buttonText,
                onClick = onPayNowClick,
                variant = TraveloButtonVariant.Secondary,
                size = TraveloButtonSize.Medium,
                leadingIcon = Icons.Default.Payments
            )
        }
    }
}

@Composable
private fun PaymentStatusChip(outcome: PaymentOutcome) {
    val label: String
    val tint: Color
    val background: Color
    val icon: ImageVector

    when (outcome) {
        PaymentOutcome.SUCCESS -> {
            label = "Paid"
            tint = SageDeep
            background = SageTint
            icon = Icons.Default.CheckCircle
        }

        PaymentOutcome.WAITING -> {
            label = "Pending"
            tint = WarningDeep
            background = WarningTint
            icon = Icons.Default.HourglassEmpty
        }

        PaymentOutcome.FAILED -> {
            label = "Failed"
            tint = DangerDeep
            background = DangerTint
            icon = Icons.Default.ErrorOutline
        }

        PaymentOutcome.EXPIRED -> {
            label = "Expired"
            tint = WarningDeep
            background = WarningTint
            icon = Icons.Default.Schedule
        }

        PaymentOutcome.CANCELLED -> {
            label = "Cancelled"
            tint = DangerDeep
            background = DangerTint
            icon = Icons.Default.Cancel
        }
    }

    androidx.compose.material3.Surface(
        shape = PillShape,
        color = background
    ) {
        Row(
            modifier = Modifier.padding(horizontal = TraveloSpacing.xs, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = tint,
                modifier = Modifier.size(14.dp)
            )
            Spacer(Modifier.width(4.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = tint
            )
        }
    }
}
