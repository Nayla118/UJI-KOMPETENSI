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
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.Review
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.ui.components.ReviewEditorDialog
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloRatingStars
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.formatDisplayDate

/**
 * Read-only history for tickets that can no longer be used for entry.
 *
 * The list is filtered from the owner-scoped `/my-bookings` response using the
 * backend's `ticket_usage_status`. No QR is rendered here: a used or expired
 * ticket is not an active e-ticket.
 */
@Composable
fun TicketHistoryScreen(
    onBackClick: () -> Unit,
    viewModel: TicketHistoryViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    var reviewTarget by remember { mutableStateOf<Booking?>(null) }
    var deleteTarget by remember { mutableStateOf<Review?>(null) }
    val errorMessage = uiState.error
        ?: uiState.errorRes?.let { stringResource(it) }
    val reviewSuccessRes = uiState.reviewSuccessRes
    val reviewSuccessMessage = if (reviewSuccessRes != null) {
        stringResource(reviewSuccessRes)
    } else {
        null
    }

    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            viewModel.loadHistory()
        }
    }

    LaunchedEffect(errorMessage) {
        if (errorMessage != null && uiState.tickets.isNotEmpty()) {
            snackbarHostState.showSnackbar(errorMessage)
            viewModel.clearError()
        }
    }

    LaunchedEffect(reviewSuccessRes) {
        if (reviewSuccessRes != null) {
            reviewTarget = null
            deleteTarget = null
            reviewSuccessMessage?.let { snackbarHostState.showSnackbar(it) }
            viewModel.clearReviewFeedback()
        }
    }

    LaunchedEffect(uiState.reviewActionError) {
        val message = uiState.reviewActionError

        if (message != null && reviewTarget == null && deleteTarget == null) {
            snackbarHostState.showSnackbar(message)
            viewModel.clearReviewFeedback()
        }
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = stringResource(R.string.ticket_history_title),
                onBackClick = onBackClick,
                actions = {
                    IconButton(onClick = { viewModel.loadHistory() }) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = stringResource(R.string.ticket_history_refresh),
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
            uiState.isLoading && uiState.tickets.isEmpty() -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .padding(horizontal = TraveloSpacing.screen)
                ) {
                    TraveloLoadingState()
                }
            }

            errorMessage != null && uiState.tickets.isEmpty() -> {
                TraveloErrorState(
                    message = errorMessage,
                    onRetry = { viewModel.loadHistory() },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = stringResource(R.string.ticket_history_load_failed),
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            uiState.tickets.isEmpty() -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentAlignment = Alignment.Center
                ) {
                    TraveloEmptyState(
                        title = stringResource(R.string.ticket_history_empty_title),
                        message = stringResource(R.string.ticket_history_empty_message),
                        icon = Icons.Default.History,
                        modifier = Modifier.padding(horizontal = TraveloSpacing.screen)
                    )
                }
            }

            else -> {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    contentPadding = PaddingValues(
                        horizontal = TraveloSpacing.screen,
                        vertical = TraveloSpacing.sm
                    ),
                    verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
                ) {
                    items(uiState.tickets, key = { it.id }) { booking ->
                        TicketHistoryCard(
                            booking = booking,
                            onRateClick = {
                                viewModel.clearReviewFeedback()
                                reviewTarget = booking
                            },
                            onDeleteClick = { review ->
                                viewModel.clearReviewFeedback()
                                deleteTarget = review
                            }
                        )
                    }
                }
            }
        }
    }
    reviewTarget?.let { booking ->
        val existing = booking.review

        ReviewEditorDialog(
            title = stringResource(
                if (existing != null) {
                    R.string.review_dialog_edit_title
                } else {
                    R.string.review_dialog_new_title
                }
            ),
            initialRating = existing?.rating ?: 5,
            initialComment = existing?.comment,
            confirmLabel = stringResource(
                if (existing != null) {
                    R.string.review_save
                } else {
                    R.string.review_publish
                }
            ),
            isSaving = uiState.isSavingReview,
            error = uiState.reviewActionError,
            onDismiss = {
                if (!uiState.isSavingReview) {
                    reviewTarget = null
                    viewModel.clearReviewFeedback()
                }
            },
            onSubmit = { rating, comment ->
                viewModel.saveReview(booking, rating, comment)
            }
        )
    }

    deleteTarget?.let { review ->
        AlertDialog(
            onDismissRequest = {
                if (!uiState.isSavingReview) {
                    deleteTarget = null
                    viewModel.clearReviewFeedback()
                }
            },
            title = {
                Text(
                    text = stringResource(R.string.review_delete_title),
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
            },
            text = {
                Column {
                    Text(
                        text = stringResource(R.string.review_delete_message),
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumMuted
                    )
                    uiState.reviewActionError?.let { message ->
                        Spacer(Modifier.height(TraveloSpacing.xs))
                        Text(
                            text = message,
                            style = MaterialTheme.typography.bodySmall,
                            color = DangerDeep
                        )
                    }
                }
            },
            confirmButton = {
                TextButton(
                    onClick = { viewModel.deleteReview(review) },
                    enabled = !uiState.isSavingReview
                ) {
                    if (uiState.isSavingReview) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = DangerDeep
                        )
                    } else {
                        Text(
                            text = stringResource(R.string.review_delete_confirm),
                            color = DangerDeep
                        )
                    }
                }
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        deleteTarget = null
                        viewModel.clearReviewFeedback()
                    },
                    enabled = !uiState.isSavingReview
                ) {
                    Text(
                        text = stringResource(R.string.review_cancel),
                        color = PlumMuted
                    )
                }
            }
        )
    }
}

@Composable
private fun TicketHistoryCard(
    booking: Booking,
    onRateClick: () -> Unit,
    onDeleteClick: (Review) -> Unit
) {
    val usageStatus = booking.ticketUsageStatus.lowercase()
    val used = usageStatus == "used"

    TraveloCard {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xs),
            verticalAlignment = Alignment.Top
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = stringResource(R.string.ticket_history_booking_number, booking.id),
                    style = MaterialTheme.typography.labelMedium,
                    color = PlumMuted
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = booking.tourPackage?.safeTitle ?: stringResource(R.string.ticket_history_tour_fallback),
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
            TicketUsageChip(usageStatus = usageStatus)
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.md),
            verticalAlignment = Alignment.CenterVertically
        ) {
            HistoryMeta(
                icon = Icons.Default.CalendarMonth,
                text = formatDisplayDate(booking.bookingDate)
            )
            HistoryMeta(
                icon = Icons.Default.People,
                text = pluralStringResource(
                    R.plurals.eticket_travelers,
                    booking.peopleCount,
                    booking.peopleCount
                )
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
                text = stringResource(R.string.eticket_total_paid),
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
            TraveloPriceText(
                amount = booking.totalPrice,
                compact = true
            )
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        val note = when {
            used && !booking.ticketUsedAt.isNullOrBlank() -> {
                stringResource(
                    R.string.ticket_history_used_on,
                    formatDisplayDate(booking.ticketUsedAt)
                )
            }
            used -> stringResource(R.string.ticket_history_used_note)
            else -> stringResource(R.string.ticket_history_expired_note)
        }
        val tone = if (used) WarningDeep else DangerDeep
        val background = if (used) WarningTint else DangerTint
        val icon = if (used) Icons.Default.CheckCircle else Icons.Default.Schedule

        Surface(
            shape = MaterialTheme.shapes.medium,
            color = background,
            border = BorderStroke(1.dp, tone.copy(alpha = 0.15f)),
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier.padding(TraveloSpacing.sm),
                verticalAlignment = Alignment.Top
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = tone,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(Modifier.width(TraveloSpacing.xs))
                Text(
                    text = note,
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumInk
                )
            }
        }

        val review = booking.review
        val canRate = used &&
            review == null &&
            (booking.canReview == true || booking.reviewStatus == "eligible")

        when {
            review != null && !review.isPublished -> {
                Spacer(Modifier.height(TraveloSpacing.sm))
                Surface(
                    shape = MaterialTheme.shapes.medium,
                    color = WarningTint,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = stringResource(R.string.review_under_moderation_short),
                        style = MaterialTheme.typography.bodySmall,
                        color = WarningDeep,
                        modifier = Modifier.padding(TraveloSpacing.sm)
                    )
                }
            }

            review != null -> {
                Spacer(Modifier.height(TraveloSpacing.sm))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TraveloRatingStars(
                        rating = review.rating.toDouble(),
                        size = 16.dp
                    )
                    Spacer(Modifier.width(TraveloSpacing.xs))
                    Text(
                        text = stringResource(R.string.review_my_rating, review.rating),
                        style = MaterialTheme.typography.labelMedium,
                        color = PlumMuted,
                        modifier = Modifier.weight(1f)
                    )
                }

                review.comment?.takeIf { it.isNotBlank() }?.let { comment ->
                    Spacer(Modifier.height(TraveloSpacing.xxs))
                    Text(
                        text = comment,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumInk,
                        maxLines = 3,
                        overflow = TextOverflow.Ellipsis
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    TextButton(onClick = onRateClick) {
                        Text(
                            text = stringResource(R.string.review_edit),
                            color = Rosewood
                        )
                    }
                    TextButton(onClick = { onDeleteClick(review) }) {
                        Text(
                            text = stringResource(R.string.review_delete),
                            color = DangerDeep
                        )
                    }
                }
            }

            canRate -> {
                Spacer(Modifier.height(TraveloSpacing.sm))
                Button(
                    onClick = onRateClick,
                    colors = ButtonDefaults.buttonColors(containerColor = Rosewood),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(stringResource(R.string.review_rate_visit))
                }
            }

            used -> {
                Spacer(Modifier.height(TraveloSpacing.xs))
                Text(
                    text = stringResource(
                        if (booking.reviewStatus == "superseded") {
                            R.string.review_superseded_short
                        } else {
                            R.string.review_not_eligible_short
                        }
                    ),
                    style = MaterialTheme.typography.labelSmall,
                    color = PlumMuted
                )
            }
        }
    }
}

@Composable
private fun TicketUsageChip(usageStatus: String) {
    val used = usageStatus == "used"
    val label = if (used) {
        stringResource(R.string.eticket_status_used)
    } else {
        stringResource(R.string.eticket_status_expired)
    }
    val tint = if (used) WarningDeep else DangerDeep
    val background = if (used) WarningTint else DangerTint
    val icon: ImageVector = if (used) Icons.Default.CheckCircle else Icons.Default.Schedule

    Surface(shape = PillShape, color = background) {
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

@Composable
private fun HistoryMeta(icon: ImageVector, text: String) {
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
