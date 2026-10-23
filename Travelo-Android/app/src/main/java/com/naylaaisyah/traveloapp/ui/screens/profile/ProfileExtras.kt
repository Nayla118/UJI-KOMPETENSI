package com.naylaaisyah.traveloapp.ui.screens.profile

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.HelpOutline
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.People
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Divider
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.BuildConfig
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloImage
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloStatusChip
import com.naylaaisyah.traveloapp.ui.components.TraveloStatusTone
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatDisplayDate

@Composable
internal fun ProfileCompletionCard(
    completion: ProfileCompletion,
    onEdit: () -> Unit
) {
    val complete = completion.missingLabels.isEmpty()
    val accent = if (complete) SageDeep else Rosewood
    val tint = if (complete) SageTint else BlushMist

    TraveloCard {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Surface(
                shape = CircleShape,
                color = tint
            ) {
                Icon(
                    imageVector = Icons.Outlined.CheckCircle,
                    contentDescription = null,
                    tint = accent,
                    modifier = Modifier
                        .padding(9.dp)
                        .size(19.dp)
                )
            }

            Spacer(Modifier.width(TraveloSpacing.sm))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = if (complete) "Profile complete" else "Profile strength",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Text(
                    text = if (complete) {
                        "Your details are ready for faster bookings."
                    } else {
                        val summary = when {
                            completion.missingPersonalLabels.isNotEmpty() ->
                                "Add ${joinProfileLabels(completion.missingPersonalLabels)}."
                            completion.missingSavedIdentity ->
                                "Save your booker identity on your next booking."
                            else -> "A little more detail makes booking smoother."
                        }
                        summary
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }

            Spacer(Modifier.width(TraveloSpacing.sm))

            Text(
                text = "${completion.percent}%",
                style = MaterialTheme.typography.titleMedium,
                color = accent
            )
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp)
                .clip(RoundedCornerShape(percent = 50))
                .background(BlushMist)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(completion.percent / 100f)
                    .fillMaxHeight()
                    .background(accent)
            )
        }

        if (completion.missingPersonalLabels.isNotEmpty()) {
            Spacer(Modifier.height(TraveloSpacing.xxs))
            TextButton(
                onClick = onEdit,
                modifier = Modifier.align(Alignment.End)
            ) {
                Text(
                    text = "Complete details",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    }
}

@Composable
internal fun QuickActionsSection(
    bookingsSubtitle: String,
    onMyBookings: () -> Unit,
    onTicketHistory: () -> Unit,
    onEditProfile: () -> Unit,
    onHelp: () -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(IntrinsicSize.Min),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
        ) {
            QuickActionCard(
                icon = Icons.Default.ConfirmationNumber,
                title = "My bookings",
                subtitle = bookingsSubtitle,
                onClick = onMyBookings,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxHeight()
            )
            QuickActionCard(
                icon = Icons.Outlined.QrCodeScanner,
                title = "Ticket history",
                subtitle = "Used and expired tickets",
                onClick = onTicketHistory,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxHeight()
            )
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(IntrinsicSize.Min),
            horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
        ) {
            QuickActionCard(
                icon = Icons.Outlined.Person,
                title = "Edit profile",
                subtitle = "Keep your details current",
                onClick = onEditProfile,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxHeight()
            )
            QuickActionCard(
                icon = Icons.AutoMirrored.Outlined.HelpOutline,
                title = "Help & support",
                subtitle = "Answers for your trip",
                onClick = onHelp,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxHeight()
            )
        }
    }
}

@Composable
private fun QuickActionCard(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    TraveloCard(
        modifier = modifier,
        onClick = onClick,
        contentPadding = PaddingValues(14.dp)
    ) {
        Surface(
            shape = CircleShape,
            color = BlushMist
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier
                    .padding(9.dp)
                    .size(18.dp)
            )
        }

        Spacer(Modifier.height(TraveloSpacing.xs))

        Text(
            text = title,
            style = MaterialTheme.typography.titleSmall,
            color = PlumInk,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
        Text(
            text = subtitle,
            style = MaterialTheme.typography.bodySmall,
            color = PlumMuted,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
internal fun NextTripCard(
    booking: Booking,
    onClick: () -> Unit
) {
    val status = profileTripStatus(booking)
    val packageTitle = booking.tourPackage?.title?.takeIf { it.isNotBlank() } ?: "Your next trip"
    val travelers = if (booking.peopleCount == 1) "1 traveler" else "${booking.peopleCount} travelers"

    TraveloCard(
        onClick = onClick,
        contentPadding = PaddingValues(0.dp)
    ) {
        Box {
            TraveloImage(
                url = booking.tourPackage?.image,
                contentDescription = null,
                shape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(132.dp)
            )

            Surface(
                shape = RoundedCornerShape(percent = 50),
                color = SurfaceWhite.copy(alpha = 0.94f),
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .padding(12.dp)
            ) {
                Text(
                    text = "UP NEXT",
                    style = MaterialTheme.typography.labelSmall,
                    color = Rosewood,
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
                )
            }

            TraveloStatusChip(
                label = status.label,
                tone = status.tone.toStatusTone(),
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(12.dp)
            )
        }

        Column(modifier = Modifier.padding(TraveloSpacing.md)) {
            Text(
                text = packageTitle,
                style = MaterialTheme.typography.titleMedium,
                color = PlumInk,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(Modifier.height(TraveloSpacing.xs))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.CalendarMonth,
                    contentDescription = null,
                    tint = PlumMuted,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    text = formatDisplayDate(booking.bookingDate),
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
                Spacer(Modifier.width(TraveloSpacing.sm))
                Icon(
                    imageVector = Icons.Outlined.People,
                    contentDescription = null,
                    tint = PlumMuted,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    text = travelers,
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            Divider(color = PlumBorder.copy(alpha = 0.75f), thickness = 1.dp)

            Spacer(Modifier.height(TraveloSpacing.sm))

            Row(verticalAlignment = Alignment.CenterVertically) {
                TraveloPriceText(
                    amount = booking.totalPrice,
                    compact = true
                )
                Spacer(Modifier.weight(1f))
                Text(
                    text = "View booking",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
                Icon(
                    imageVector = Icons.Default.ChevronRight,
                    contentDescription = null,
                    tint = Rosewood,
                    modifier = Modifier.size(18.dp)
                )
            }
        }
    }
}

private fun ProfileTripTone.toStatusTone(): TraveloStatusTone = when (this) {
    ProfileTripTone.Neutral -> TraveloStatusTone.Neutral
    ProfileTripTone.Success -> TraveloStatusTone.Success
    ProfileTripTone.Warning -> TraveloStatusTone.Warning
    ProfileTripTone.Danger -> TraveloStatusTone.Danger
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun PrivacyDataSheet(
    savedIdentity: SavedIdentity?,
    onDismiss: () -> Unit,
    onDeleteIdentity: () -> Unit
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = VanillaCream
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(horizontal = TraveloSpacing.screen)
                .padding(bottom = TraveloSpacing.lg)
        ) {
            Text(
                text = "Privacy & data",
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "You control the details Travelo keeps for your account.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            ProfileInfoBlock(
                icon = Icons.Outlined.Person,
                title = "Profile details",
                body = "Your name, phone number, birth date, and home city are used to personalise bookings. Your email is managed by the verified sign-in provider and cannot be edited here."
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ProfileInfoBlock(
                icon = Icons.Outlined.Lock,
                title = "Saved booker identity",
                body = if (savedIdentity == null) {
                    "No account-level identity is saved yet. Additional travellers are never saved automatically."
                } else {
                    "${savedIdentityTypeLabel(savedIdentity)} · ${
                        maskSavedIdentityNumber(savedIdentity.identityNumber)
                    }. Only your booker identity is saved; additional travellers enter their own details for each booking."
                }
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ProfileInfoBlock(
                icon = Icons.Outlined.Description,
                title = "Private documents",
                body = "Identity documents are stored privately, not in the public web folder. A successful upload waits for admin review and is never verified automatically. Booking-level documents are purged after 90 days; your saved identity copy remains until you replace or delete it."
            )

            if (savedIdentity != null) {
                Spacer(Modifier.height(TraveloSpacing.md))
                TraveloButton(
                    text = "Delete saved identity",
                    onClick = onDeleteIdentity,
                    variant = TraveloButtonVariant.Secondary,
                    leadingIcon = Icons.Outlined.Delete
                )
            }

            Spacer(Modifier.height(TraveloSpacing.xs))

            TextButton(
                onClick = onDismiss,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = "Close",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun HelpSupportSheet(onDismiss: () -> Unit) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = VanillaCream
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(horizontal = TraveloSpacing.screen)
                .padding(bottom = TraveloSpacing.lg)
        ) {
            Text(
                text = "Help & support",
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "Quick answers for the things travellers ask most.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            ProfileInfoBlock(
                icon = Icons.Default.ConfirmationNumber,
                title = "Manage a booking",
                body = "Open My bookings to see payment status, complete identity documents, or open an active e-ticket."
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ProfileInfoBlock(
                icon = Icons.Outlined.Payments,
                title = "Payments",
                body = "Outstanding payments can be completed from the booking card. Payment details are handled by the payment provider and are never stored in this app."
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ProfileInfoBlock(
                icon = Icons.Outlined.QrCodeScanner,
                title = "At the gate",
                body = "Show the e-ticket QR code on your visit date. A ticket can only be redeemed once, and staff may check it against their scanner."
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ProfileInfoBlock(
                icon = Icons.Outlined.Shield,
                title = "Identity documents",
                body = "Documents are private and reviewed by an admin. Uploading a document does not mark it verified automatically. You can review saved identity details in Privacy & data."
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloButton(
                text = "Got it",
                onClick = onDismiss,
                variant = TraveloButtonVariant.Secondary
            )
        }
    }
}

@Composable
private fun ProfileInfoBlock(
    icon: ImageVector,
    title: String,
    body: String
) {
    Surface(
        shape = CardShape,
        color = SurfaceWhite,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(TraveloSpacing.md),
            verticalAlignment = Alignment.Top
        ) {
            Surface(
                shape = CircleShape,
                color = BlushMist
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = Rosewood,
                    modifier = Modifier
                        .padding(9.dp)
                        .size(18.dp)
                )
            }

            Spacer(Modifier.width(TraveloSpacing.sm))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = body,
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }
    }
}

@Composable
internal fun AboutTraveloDialog(onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = SurfaceWhite,
        shape = CardShape,
        title = {
            Text(
                text = "About Travelo",
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )
        },
        text = {
            Column {
                Text(
                    text = "Version ${BuildConfig.VERSION_NAME}",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                Text(
                    text = "Travelo helps you discover destinations, manage bookings, and keep travel documents private. Booking and payment status always comes from the Travelo server.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumMuted
                )
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text(
                    text = "Close",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    )
}
