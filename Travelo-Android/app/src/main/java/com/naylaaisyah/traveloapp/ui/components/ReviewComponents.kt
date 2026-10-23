package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Review
import com.naylaaisyah.traveloapp.data.models.ReviewEligibility
import com.naylaaisyah.traveloapp.data.models.ReviewSummary
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.Gray200
import com.naylaaisyah.traveloapp.ui.theme.Gray300
import com.naylaaisyah.traveloapp.ui.theme.Gray500
import com.naylaaisyah.traveloapp.ui.theme.Gray600
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.RoseTint
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import java.util.Locale

@Composable
fun TraveloRatingStars(
    rating: Double?,
    modifier: Modifier = Modifier,
    size: Dp = 15.dp
) {
    val normalized = (rating ?: 0.0).coerceIn(0.0, 5.0)

    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(1.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        for (star in 1..5) {
            Icon(
                imageVector = Icons.Filled.Star,
                contentDescription = null,
                tint = if (star <= normalized) Rosewood else Gray300,
                modifier = Modifier.size(size)
            )
        }
    }
}

/**
 * Public aggregate. Hidden/moderated reviews are excluded by the backend
 * before this data ever reaches the app.
 */
@Composable
fun TraveloReviewSummaryCard(
    summary: ReviewSummary,
    modifier: Modifier = Modifier
) {
    val count = summary.count
    val average = summary.average
    val averageText = if (count > 0 && average != null) {
        String.format(Locale.US, "%.1f", average)
    } else {
        "-"
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(18.dp),
        color = SurfaceWhite,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Column(modifier = Modifier.padding(TraveloSpacing.md)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(
                    modifier = Modifier.width(96.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = averageText,
                        style = MaterialTheme.typography.headlineLarge,
                        color = PlumInk,
                        fontWeight = FontWeight.Bold
                    )
                    TraveloRatingStars(rating = average, size = 14.dp)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = if (count > 0) {
                            pluralStringResource(R.plurals.review_count, count, count)
                        } else {
                            stringResource(R.string.review_no_ratings)
                        },
                        style = MaterialTheme.typography.labelSmall,
                        color = PlumMuted,
                        textAlign = TextAlign.Center
                    )
                }

                Spacer(Modifier.width(TraveloSpacing.md))

                Column(modifier = Modifier.weight(1f)) {
                    for (star in 5 downTo 1) {
                        val value = summary.distribution[star.toString()] ?: 0
                        val fraction = if (count > 0) value.toFloat() / count else 0f

                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 2.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = star.toString(),
                                style = MaterialTheme.typography.labelSmall,
                                color = PlumMuted,
                                modifier = Modifier.width(12.dp)
                            )
                            Spacer(Modifier.width(6.dp))
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(6.dp)
                                    .clip(RoundedCornerShape(3.dp))
                                    .background(Gray200)
                            ) {
                                if (fraction > 0f) {
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth(fraction.coerceIn(0f, 1f))
                                            .fillMaxHeight()
                                            .background(Rosewood)
                                    )
                                }
                            }
                            Spacer(Modifier.width(6.dp))
                            Text(
                                text = value.toString(),
                                style = MaterialTheme.typography.labelSmall,
                                color = PlumMuted,
                                textAlign = TextAlign.End,
                                modifier = Modifier.width(18.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun TraveloReviewCard(
    review: Review,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Surface(
                shape = CircleShape,
                color = BlushMist,
                modifier = Modifier.size(38.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text(
                        text = review.reviewerName
                            ?.trim()
                            ?.firstOrNull()
                            ?.uppercase()
                            ?: "T",
                        style = MaterialTheme.typography.titleSmall,
                        color = Rosewood
                    )
                }
            }

            Spacer(Modifier.width(TraveloSpacing.xs))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = review.reviewerName
                        ?.takeIf { it.isNotBlank() }
                        ?: stringResource(R.string.review_guest_name),
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = formatDisplayDate(review.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = Gray500
                )
            }

            if (review.verifiedVisit) {
                Surface(shape = PillShape, color = SageTint) {
                    Row(
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Filled.CheckCircle,
                            contentDescription = null,
                            tint = SageDeep,
                            modifier = Modifier.size(12.dp)
                        )
                        Spacer(Modifier.width(3.dp))
                        Text(
                            text = stringResource(R.string.review_verified_visit),
                            style = MaterialTheme.typography.labelSmall,
                            color = SageDeep
                        )
                    }
                }
            }
        }

        Spacer(Modifier.height(TraveloSpacing.xs))

        TraveloRatingStars(rating = review.rating.toDouble(), size = 14.dp)

        val comment = review.comment?.trim().orEmpty()
        if (comment.isNotEmpty()) {
            Spacer(Modifier.height(TraveloSpacing.xs))
            Text(
                text = comment,
                style = MaterialTheme.typography.bodyMedium,
                color = PlumInk
            )
        }
    }
}

/**
 * Explains why a destination can or cannot be rated. Booking-derived states
 * are shown honestly rather than offering a button that the server would
 * reject.
 */
@Composable
fun TraveloReviewEligibilityCard(
    eligibility: ReviewEligibility,
    onRateClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val status = eligibility.status
    val hidden = eligibility.review?.status == "hidden"

    val title: String
    val message: String
    val background: Color
    val foreground: Color

    when {
        status == "eligible" -> {
            title = stringResource(R.string.review_status_eligible_title)
            message = stringResource(R.string.review_status_eligible_message)
            background = RoseTint
            foreground = Rosewood
        }
        status == "reviewed" && hidden -> {
            title = stringResource(R.string.review_under_moderation_title)
            message = stringResource(R.string.review_under_moderation_message)
            background = WarningTint
            foreground = WarningDeep
        }
        status == "reviewed" -> {
            title = stringResource(R.string.review_already_reviewed_title)
            message = stringResource(R.string.review_already_reviewed_message)
            background = SageTint
            foreground = SageDeep
        }
        status == "not_checked_in" -> {
            title = stringResource(R.string.review_not_checked_in_title)
            message = stringResource(R.string.review_not_checked_in_message)
            background = WarningTint
            foreground = WarningDeep
        }
        status == "unpaid" -> {
            title = stringResource(R.string.review_unpaid_title)
            message = stringResource(R.string.review_unpaid_message)
            background = WarningTint
            foreground = WarningDeep
        }
        status == "cancelled" -> {
            title = stringResource(R.string.review_cancelled_title)
            message = stringResource(R.string.review_cancelled_message)
            background = WarningTint
            foreground = WarningDeep
        }
        else -> {
            title = stringResource(R.string.review_not_booked_title)
            message = stringResource(R.string.review_not_booked_message)
            background = SurfaceWhite
            foreground = PlumMuted
        }
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        color = background,
        border = BorderStroke(1.dp, foreground.copy(alpha = 0.18f))
    ) {
        Column(modifier = Modifier.padding(TraveloSpacing.md)) {
            Row(verticalAlignment = Alignment.Top) {
                Icon(
                    imageVector = Icons.Filled.CheckCircle,
                    contentDescription = null,
                    tint = foreground,
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
                        text = message,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted
                    )
                }
            }

            if (status == "eligible") {
                Spacer(Modifier.height(TraveloSpacing.sm))
                Button(
                    onClick = onRateClick,
                    colors = ButtonDefaults.buttonColors(containerColor = Rosewood),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(stringResource(R.string.review_rate_visit))
                }
            }

            if (status == "reviewed" && !hidden) {
                // A visit can only be rated once. Editing and deleting stay
                // available from Ticket History, so this card never offers a
                // second rating action.
                eligibility.review?.let { review ->
                    Spacer(Modifier.height(TraveloSpacing.xs))
                    TraveloRatingStars(rating = review.rating.toDouble(), size = 16.dp)
                }
            }

            if (status == "reviewed" && hidden) {
                Text(
                    text = stringResource(R.string.review_hidden_help),
                    style = MaterialTheme.typography.labelSmall,
                    color = WarningDeep,
                    modifier = Modifier.padding(top = TraveloSpacing.xxs)
                )
            }
        }
    }
}

@Composable
fun ReviewEditorDialog(
    title: String,
    initialRating: Int,
    initialComment: String?,
    confirmLabel: String,
    isSaving: Boolean,
    error: String?,
    onDismiss: () -> Unit,
    onSubmit: (Int, String?) -> Unit
) {
    var rating by remember(initialRating) { mutableIntStateOf(initialRating.coerceIn(1, 5)) }
    var comment by remember(initialComment) { mutableStateOf(initialComment.orEmpty()) }

    AlertDialog(
        onDismissRequest = { if (!isSaving) onDismiss() },
        title = {
            Text(
                text = title,
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )
        },
        text = {
            Column {
                Text(
                    text = stringResource(R.string.review_stars_prompt),
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumMuted
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    for (star in 1..5) {
                        Icon(
                            imageVector = Icons.Filled.Star,
                            contentDescription = stringResource(R.string.review_star_content, star),
                            tint = if (star <= rating) Rosewood else Gray300,
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .clickable(enabled = !isSaving) { rating = star }
                                .padding(5.dp)
                        )
                    }
                }

                Spacer(Modifier.height(TraveloSpacing.sm))
                OutlinedTextField(
                    value = comment,
                    onValueChange = { comment = it.take(1000) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text(stringResource(R.string.review_comment_label)) },
                    placeholder = { Text(stringResource(R.string.review_comment_placeholder)) },
                    enabled = !isSaving,
                    minLines = 3,
                    maxLines = 5
                )
                Text(
                    text = stringResource(R.string.review_comment_count, comment.length),
                    style = MaterialTheme.typography.labelSmall,
                    color = Gray500,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 4.dp),
                    textAlign = TextAlign.End
                )

                if (!error.isNullOrBlank()) {
                    Spacer(Modifier.height(TraveloSpacing.xs))
                    Text(
                        text = error,
                        style = MaterialTheme.typography.bodySmall,
                        color = DangerDeep
                    )
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onSubmit(rating, comment.trim().ifBlank { null }) },
                enabled = !isSaving && rating in 1..5
            ) {
                if (isSaving) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(18.dp),
                        strokeWidth = 2.dp,
                        color = Rosewood
                    )
                } else {
                    Text(confirmLabel, color = Rosewood)
                }
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !isSaving) {
                Text(stringResource(R.string.review_cancel), color = Gray600)
            }
        },
        containerColor = SurfaceWhite
    )
}
