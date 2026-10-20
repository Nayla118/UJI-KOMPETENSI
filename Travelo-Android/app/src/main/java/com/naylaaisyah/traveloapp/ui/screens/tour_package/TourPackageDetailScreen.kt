package com.naylaaisyah.traveloapp.ui.screens.tour_package

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
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.data.models.TourPackageDetail
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloImage
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Tour package detail. The previous version ended with a hardcoded "Included"
 * list (accommodation, guide, transport, meals) that the API never returned,
 * so it was removed rather than restyled. What remains is duration, group size,
 * rating, description, the linked destination and the real price.
 */
@Composable
fun TourPackageDetailScreen(
    tourPackageId: Int,
    onBackClick: () -> Unit,
    onBookNowClick: (Int, String, Int, Int) -> Unit,
    viewModel: TourPackageDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(tourPackageId) {
        viewModel.loadTourPackage(tourPackageId)
    }

    Scaffold(
        containerColor = VanillaCream,
        bottomBar = {
            val tourPackage = uiState.tourPackage
            if (tourPackage != null) {
                BookingBar(
                    tourPackage = tourPackage,
                    onBookNow = {
                        onBookNowClick(
                            tourPackage.id,
                            tourPackage.title,
                            tourPackage.price,
                            tourPackage.maxPeople
                        )
                    }
                )
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            val tourPackage = uiState.tourPackage

            when {
                tourPackage != null -> TourPackageContent(
                    tourPackage = tourPackage,
                    onBackClick = onBackClick
                )

                uiState.error != null -> DetailError(
                    message = uiState.error.orEmpty(),
                    onBackClick = onBackClick,
                    onRetry = { viewModel.loadTourPackage(tourPackageId) }
                )

                else -> DetailLoading(onBackClick = onBackClick)
            }
        }
    }
}

@Composable
private fun BookingBar(
    tourPackage: TourPackageDetail,
    onBookNow: () -> Unit
) {
    Surface(color = SurfaceWhite, shadowElevation = 8.dp) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(
                    horizontal = TraveloSpacing.screen,
                    vertical = TraveloSpacing.sm
                )
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.Bottom,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "Price per person",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
                TraveloPriceText(
                    amount = tourPackage.price.toDouble(),
                    emphasized = true
                )
            }
            Spacer(Modifier.height(TraveloSpacing.xs))
            TraveloButton(text = "Book now", onClick = onBookNow)
        }
    }
}

@Composable
private fun TourPackageContent(
    tourPackage: TourPackageDetail,
    onBackClick: () -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = TraveloSpacing.lg)
    ) {
        item {
            Box {
                TraveloImage(
                    url = tourPackage.image,
                    contentDescription = tourPackage.title,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(250.dp),
                    shape = RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp)
                )

                Surface(
                    shape = CircleShape,
                    color = SurfaceWhite.copy(alpha = 0.92f),
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .padding(TraveloSpacing.sm)
                ) {
                    TraveloIconButton(
                        icon = Icons.Default.ArrowBack,
                        contentDescription = "Back",
                        onClick = onBackClick
                    )
                }
            }
        }

        item {
            Column(modifier = Modifier.padding(horizontal = TraveloSpacing.screen)) {
                Spacer(Modifier.height(TraveloSpacing.md))

                Text(
                    text = tourPackage.title,
                    style = MaterialTheme.typography.headlineSmall,
                    color = PlumInk
                )

                Spacer(Modifier.height(TraveloSpacing.sm))

                Row(
                    horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.md),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    val rating = tourPackage.rating ?: 0.0
                    if (rating > 0.0) {
                        MetaItem(
                            icon = Icons.Default.Star,
                            text = String.format(java.util.Locale.US, "%.1f", rating)
                        )
                    }
                    MetaItem(
                        icon = Icons.Default.DateRange,
                        text = if (tourPackage.durationDays == 1) {
                            "1 day"
                        } else {
                            "${tourPackage.durationDays} days"
                        }
                    )
                    MetaItem(
                        icon = Icons.Default.Person,
                        text = "Up to ${tourPackage.maxPeople}"
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                Text(
                    text = "About this tour",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                Text(
                    text = tourPackage.description
                        ?.takeIf { it.isNotBlank() }
                        ?: "No description has been added for this tour package yet.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk,
                    lineHeight = 22.sp
                )

                tourPackage.destination?.let { destination ->
                    Spacer(Modifier.height(TraveloSpacing.lg))

                    Text(
                        text = "Destination",
                        style = MaterialTheme.typography.titleLarge,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(TraveloSpacing.xs))

                    Surface(
                        shape = CardShape,
                        color = SurfaceWhite,
                        border = BorderStroke(1.dp, PlumBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(TraveloSpacing.sm),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            TraveloImage(
                                url = destination.image,
                                contentDescription = destination.name,
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.size(64.dp)
                            )
                            Column(
                                modifier = Modifier
                                    .weight(1f)
                                    .padding(start = TraveloSpacing.sm)
                            ) {
                                Text(
                                    text = destination.name,
                                    style = MaterialTheme.typography.titleSmall,
                                    color = PlumInk,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )
                                Text(
                                    text = "${destination.city}, ${destination.country}",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = PlumMuted
                                )
                            }
                        }
                    }
                }

                Spacer(Modifier.height(TraveloSpacing.lg))
            }
        }
    }
}

@Composable
private fun MetaItem(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    text: String
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Rosewood,
            modifier = Modifier.size(16.dp)
        )
        Spacer(Modifier.size(4.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumMuted
        )
    }
}

@Composable
private fun DetailLoading(onBackClick: () -> Unit) {
    Column(modifier = Modifier.fillMaxSize()) {
        DetailTopRow(title = "Tour package", onBackClick = onBackClick)
        TraveloLoadingState(
            modifier = Modifier.padding(horizontal = TraveloSpacing.screen)
        )
    }
}

@Composable
private fun DetailError(
    message: String,
    onBackClick: () -> Unit,
    onRetry: () -> Unit
) {
    Column(modifier = Modifier.fillMaxSize()) {
        DetailTopRow(title = "Tour package", onBackClick = onBackClick)
        TraveloErrorState(
            message = message,
            onRetry = onRetry,
            title = "Couldn't load this tour package",
            horizontalPadding = TraveloSpacing.screen
        )
    }
}

@Composable
private fun DetailTopRow(title: String, onBackClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(start = TraveloSpacing.xs, end = TraveloSpacing.screen, top = TraveloSpacing.xs),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xxs)
    ) {
        TraveloIconButton(
            icon = Icons.Default.ArrowBack,
            contentDescription = "Back",
            onClick = onBackClick
        )
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            color = PlumInk
        )
    }
}
