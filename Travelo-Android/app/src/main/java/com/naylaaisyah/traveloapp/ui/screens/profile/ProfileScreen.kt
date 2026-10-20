package com.naylaaisyah.traveloapp.ui.screens.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.SubcomposeAsyncImage
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.screens.auth.AuthViewModel
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.FieldShape
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Honest profile screen: only actions that actually work are shown.
 * The earlier version offered Personal Information, Security, Notifications,
 * Saved Destinations, Payment Methods, Help Center, Terms and Edit Profile,
 * all wired to empty lambdas. Those were removed rather than left as dead
 * controls; editing profile data needs its own API and screen.
 */
@Composable
fun ProfileScreen(
    onBackClick: () -> Unit,
    onLogout: () -> Unit,
    onMyBookings: () -> Unit = {},
    viewModel: ProfileViewModel = hiltViewModel(),
    authViewModel: AuthViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val displayName = uiState.userName?.takeIf { it.isNotBlank() } ?: "Traveler"
    val displayEmail = uiState.userEmail?.takeIf { it.isNotBlank() } ?: "No email on file"

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Profile",
                onBackClick = onBackClick
            )
        },
        containerColor = VanillaCream
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = TraveloSpacing.screen)
        ) {
            Spacer(Modifier.height(TraveloSpacing.xs))

            ProfileHeader(
                displayName = displayName,
                userEmail = displayEmail,
                userPhoto = uiState.userPhoto
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            Text(
                text = "Account",
                style = MaterialTheme.typography.labelLarge,
                color = Rosewood,
                modifier = Modifier.padding(start = TraveloSpacing.xxs, bottom = TraveloSpacing.xs)
            )

            TraveloCard(contentPadding = PaddingValues(0.dp)) {
                ProfileActionRow(
                    title = "My bookings",
                    subtitle = "View bookings and payment status",
                    onClick = onMyBookings
                )
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloButton(
                text = "Log out",
                onClick = {
                    authViewModel.logout()
                    onLogout()
                },
                variant = TraveloButtonVariant.Secondary,
                leadingIcon = Icons.AutoMirrored.Filled.Logout
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            Text(
                text = "Signed in as $displayEmail",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(Modifier.height(TraveloSpacing.xl))
        }
    }
}

@Composable
private fun ProfileHeader(
    displayName: String,
    userEmail: String,
    userPhoto: String?
) {
    val initials = initialsOf(displayName)

    Surface(
        shape = HeroShape,
        color = BlushMist,
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(TraveloSpacing.lg),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Box(
                modifier = Modifier
                    .size(96.dp)
                    .clip(CircleShape)
                    .background(SurfaceWhite),
                contentAlignment = Alignment.Center
            ) {
                if (!userPhoto.isNullOrBlank()) {
                    SubcomposeAsyncImage(
                        model = userPhoto,
                        contentDescription = "Profile photo",
                        modifier = Modifier.fillMaxSize(),
                        contentScale = ContentScale.Crop,
                        loading = { InitialsAvatar(initials) },
                        error = { InitialsAvatar(initials) }
                    )
                } else {
                    InitialsAvatar(initials)
                }
            }

            Spacer(Modifier.height(TraveloSpacing.md))

            Text(
                text = displayName,
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk,
                textAlign = TextAlign.Center
            )

            Spacer(Modifier.height(TraveloSpacing.xxs))

            Text(
                text = userEmail,
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted,
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
private fun InitialsAvatar(initials: String) {
    Text(
        text = initials,
        style = MaterialTheme.typography.headlineLarge,
        color = Rosewood,
        textAlign = TextAlign.Center
    )
}

@Composable
private fun ProfileActionRow(
    title: String,
    subtitle: String,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(TraveloSpacing.md),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Surface(
            shape = FieldShape,
            color = BlushMist
        ) {
            Icon(
                imageVector = Icons.Default.ConfirmationNumber,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier
                    .padding(10.dp)
                    .size(20.dp)
            )
        }

        Spacer(Modifier.width(TraveloSpacing.sm))

        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Text(
                text = subtitle,
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
        }

        Spacer(Modifier.width(TraveloSpacing.xs))

        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = null,
            tint = PlumMuted,
            modifier = Modifier.size(20.dp)
        )
    }
}

private fun initialsOf(name: String): String {
    val initials = name
        .trim()
        .split(Regex("\\s+"))
        .mapNotNull { part -> part.firstOrNull()?.uppercaseChar() }
        .take(2)
        .joinToString("")
    return initials.ifBlank { "T" }
}
