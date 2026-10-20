package com.naylaaisyah.traveloapp.ui.screens.destination

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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloImage
import com.naylaaisyah.traveloapp.ui.components.TraveloInlineLoading
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloRatingBadge
import com.naylaaisyah.traveloapp.ui.components.TraveloSectionHeader
import com.naylaaisyah.traveloapp.ui.components.TraveloTourPackageRow
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Destination detail uses only fields the backend returns: name, city/country,
 * rating, description, photo, and the tour packages linked to this
 * destination. There is no invented facilities list or "best time to visit"
 * copy.
 */
@Composable
fun DestinationDetailScreen(
    destinationId: Int,
    onBackClick: () -> Unit,
    onTourPackageClick: (Int) -> Unit,
    viewModel: DestinationDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(destinationId) {
        viewModel.loadDestination(destinationId)
    }

    Scaffold(containerColor = VanillaCream) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            val destination = uiState.destination

            when {
                destination != null -> DestinationContent(
                    state = uiState,
                    onBackClick = onBackClick,
                    onTourPackageClick = onTourPackageClick,
                    onRetryTours = viewModel::retryTourPackages
                )

                uiState.error != null -> DetailError(
                    message = uiState.error.orEmpty(),
                    onBackClick = onBackClick,
                    onRetry = { viewModel.loadDestination(destinationId) }
                )

                else -> DetailLoading(onBackClick = onBackClick)
            }
        }
    }
}

@Composable
private fun DestinationContent(
    state: DestinationDetailUiState,
    onBackClick: () -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onRetryTours: () -> Unit
) {
    val destination = state.destination ?: return

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = TraveloSpacing.xl)
    ) {
        item {
            Box {
                TraveloImage(
                    url = destination.image,
                    contentDescription = destination.name,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(260.dp),
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

                TraveloRatingBadge(
                    rating = destination.rating,
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(TraveloSpacing.md)
                )
            }
        }

        item {
            Column(modifier = Modifier.padding(horizontal = TraveloSpacing.screen)) {
                Spacer(Modifier.height(TraveloSpacing.md))

                Text(
                    text = destination.name,
                    style = MaterialTheme.typography.headlineSmall,
                    color = PlumInk
                )

                Spacer(Modifier.height(6.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.LocationOn,
                        contentDescription = null,
                        tint = Rosewood,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(Modifier.size(4.dp))
                    Text(
                        text = "${destination.city}, ${destination.country}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumMuted
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                Text(
                    text = "About",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                Text(
                    text = destination.description
                        ?.takeIf { it.isNotBlank() }
                        ?: "No description has been added for this destination yet.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk,
                    lineHeight = 22.sp
                )

                Spacer(Modifier.height(TraveloSpacing.lg))

                TraveloSectionHeader(title = "Tour packages")
                Spacer(Modifier.height(TraveloSpacing.xxs))
            }
        }

        when {
            state.toursError != null -> {
                item {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.xs),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Couldn't load tour packages.",
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted,
                            modifier = Modifier.weight(1f),
                            textAlign = TextAlign.Start
                        )
                        TextButton(onClick = onRetryTours) {
                            Text(
                                text = "Retry",
                                style = MaterialTheme.typography.labelLarge,
                                color = Rosewood
                            )
                        }
                    }
                }
            }

            state.isLoading -> {
                item { TraveloInlineLoading() }
            }

            state.tourPackages.isEmpty() -> {
                item {
                    TraveloEmptyState(
                        title = "No tour packages yet",
                        message = "There are no tour packages for this destination right now.",
                        horizontalPadding = TraveloSpacing.screen
                    )
                }
            }

            else -> {
                items(state.tourPackages, key = { it.id }) { tourPackage ->
                    TraveloTourPackageRow(
                        tourPackage = tourPackage,
                        onClick = { onTourPackageClick(tourPackage.id) },
                        modifier = Modifier.padding(
                            horizontal = TraveloSpacing.screen,
                            vertical = TraveloSpacing.xxs
                        )
                    )
                }
            }
        }
    }
}

@Composable
private fun DetailLoading(onBackClick: () -> Unit) {
    Column(modifier = Modifier.fillMaxSize()) {
        DetailTopRow(title = "Destination", onBackClick = onBackClick)
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
        DetailTopRow(title = "Destination", onBackClick = onBackClick)
        TraveloErrorState(
            message = message,
            onRetry = onRetry,
            title = "Couldn't load this destination",
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
