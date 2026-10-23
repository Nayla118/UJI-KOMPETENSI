package com.naylaaisyah.traveloapp.ui.screens.home

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
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.ui.components.TraveloBottomNav
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloDestinationCard
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloSearchBar
import com.naylaaisyah.traveloapp.ui.components.TraveloSectionHeader
import com.naylaaisyah.traveloapp.ui.components.TraveloTourPackageCard
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

@OptIn(ExperimentalMaterialApi::class)
@Composable
fun HomeScreen(
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onProfileClick: () -> Unit,
    onMyBookingsClick: () -> Unit,
    onExploreClick: () -> Unit,
    onCompleteDocuments: (Int) -> Unit = {},
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val pendingDocumentJob = uiState.pendingDocumentJobs.firstOrNull()

    // Profile edits update the cached session immediately. Refresh the greeting
    // whenever Home returns to the foreground so it never shows a stale name.
    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            viewModel.refreshUserInfo()
        }
    }

    val hasContent = uiState.popularDestinations.isNotEmpty() || uiState.featuredTours.isNotEmpty()
    val showInitialLoading = uiState.isLoading && !hasContent
    val showError = uiState.error != null && !hasContent

    // One hero destination, then a small grid of the rest. The API decides the
    // order; the UI does not invent a "popular" ranking.
    val heroDestination = remember(uiState.popularDestinations) {
        uiState.popularDestinations.firstOrNull { it.isPopular }
            ?: uiState.popularDestinations.firstOrNull()
    }
    val otherDestinations = remember(uiState.popularDestinations) {
        uiState.popularDestinations
            .filter { it.id != heroDestination?.id }
            .take(4)
    }
    val tours = remember(uiState.featuredTours) { uiState.featuredTours.take(4) }

    val snackbarHostState = remember { SnackbarHostState() }
    val pullRefreshState = rememberPullRefreshState(
        refreshing = uiState.isLoading,
        onRefresh = viewModel::refresh
    )

    // A refresh can fail while usable content is already on screen. In that
    // case the full-page error is suppressed, so surface the message instead
    // of failing silently.
    LaunchedEffect(uiState.error, hasContent) {
        val message = uiState.error
        if (message != null && hasContent) {
            snackbarHostState.showSnackbar(message)
            viewModel.clearError()
        }
    }

    Scaffold(
        containerColor = VanillaCream,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        bottomBar = {
            TraveloBottomNav(
                currentRoute = "home",
                onSelect = { route ->
                    when (route) {
                        "explore" -> onExploreClick()
                        "my_bookings" -> onMyBookingsClick()
                        "profile" -> onProfileClick()
                    }
                }
            )
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .pullRefresh(pullRefreshState)
        ) {
            LazyVerticalGrid(
                columns = GridCells.Adaptive(minSize = 150.dp),
                modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(
                start = TraveloSpacing.screen,
                end = TraveloSpacing.screen,
                top = 8.dp,
                bottom = TraveloSpacing.lg
            ),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item(span = { GridItemSpan(maxLineSpan) }) {
                HomeHeader(
                    userName = uiState.userName,
                    userPhoto = uiState.userPhoto,
                    onProfileClick = onProfileClick
                )
            }

            if (pendingDocumentJob != null) {
                item(span = { GridItemSpan(maxLineSpan) }) {
                    TraveloCard {
                        Text(
                            text = if (uiState.pendingDocumentJobs.size == 1) {
                                stringResource(R.string.documents_banner_one)
                            } else {
                                stringResource(
                                    R.string.documents_banner_many,
                                    uiState.pendingDocumentJobs.size
                                )
                            },
                            style = MaterialTheme.typography.titleSmall,
                            color = PlumInk
                        )
                        Spacer(Modifier.height(2.dp))
                        Text(
                            text = pendingDocumentJob.tourTitle
                                .ifBlank { "Booking #${pendingDocumentJob.bookingId}" },
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted
                        )
                        Spacer(Modifier.height(8.dp))
                        TraveloButton(
                            text = stringResource(R.string.documents_action),
                            onClick = {
                                onCompleteDocuments(pendingDocumentJob.bookingId)
                            },
                            variant = TraveloButtonVariant.Secondary,
                            size = TraveloButtonSize.Medium,
                            leadingIcon = Icons.Default.Description
                        )
                    }
                }
            }

            item(span = { GridItemSpan(maxLineSpan) }) {
                Column(modifier = Modifier.padding(top = 8.dp)) {
                    Text(
                        text = "Where to next?",
                        style = MaterialTheme.typography.displaySmall,
                        color = PlumInk
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                    Text(
                        text = "Destinations and ready-made trips, picked from what is available now.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumMuted
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    TraveloSearchBar(
                        placeholder = "Search destinations",
                        onClick = onExploreClick
                    )
                }
            }

            when {
                showInitialLoading -> {
                    item(span = { GridItemSpan(maxLineSpan) }) {
                        TraveloLoadingState(modifier = Modifier.padding(top = 8.dp))
                    }
                }

                showError -> {
                    item(span = { GridItemSpan(maxLineSpan) }) {
                        TraveloErrorState(
                            message = uiState.error.orEmpty(),
                            onRetry = viewModel::refresh,
                            title = "Couldn't load your feed",
                            horizontalPadding = 4.dp
                        )
                    }
                }

                else -> {
                    if (heroDestination != null) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloDestinationCard(
                                destination = heroDestination,
                                onClick = { onDestinationClick(heroDestination.id) },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(236.dp)
                                    .padding(top = 4.dp)
                            )
                        }
                    }

                    if (otherDestinations.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Destinations",
                                actionLabel = "See all",
                                onAction = onExploreClick,
                                modifier = Modifier.padding(top = 8.dp)
                            )
                        }
                        items(
                            items = otherDestinations,
                            key = { destination: Destination -> "destination-${destination.id}" }
                        ) { destination ->
                            TraveloDestinationCard(
                                destination = destination,
                                onClick = { onDestinationClick(destination.id) },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(180.dp)
                            )
                        }
                    }

                    if (tours.isNotEmpty()) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloSectionHeader(
                                title = "Tour packages",
                                actionLabel = "See all",
                                onAction = onExploreClick,
                                modifier = Modifier.padding(top = 12.dp)
                            )
                        }
                        items(
                            items = tours,
                            key = { tourPackage -> "tour-${tourPackage.id}" }
                        ) { tourPackage ->
                            TraveloTourPackageCard(
                                tourPackage = tourPackage,
                                onClick = { onTourPackageClick(tourPackage.id) }
                            )
                        }
                    }

                    if (heroDestination == null && tours.isEmpty() && !uiState.isLoading) {
                        item(span = { GridItemSpan(maxLineSpan) }) {
                            TraveloEmptyState(
                                title = "Nothing to show yet",
                                message = "There are no destinations or tour packages available right now.",
                                actionLabel = "Refresh",
                                onAction = viewModel::refresh,
                                horizontalPadding = 4.dp
                            )
                        }
                    }
                }
            }
            }

            PullRefreshIndicator(
                refreshing = uiState.isLoading,
                state = pullRefreshState,
                modifier = Modifier.align(Alignment.TopCenter),
                backgroundColor = SurfaceWhite,
                contentColor = Rosewood
            )
        }
    }
}

@Composable
private fun HomeHeader(
    userName: String,
    userPhoto: String?,
    onProfileClick: () -> Unit
) {
    val greeting = remember {
        val hour = java.time.LocalTime.now().hour
        when {
            hour < 11 -> "Good morning"
            hour < 18 -> "Good afternoon"
            else -> "Good evening"
        }
    }
    val displayName = userName.ifBlank { "traveller" }
    val initial = displayName.trim().firstOrNull()?.uppercase() ?: "T"

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 12.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = greeting,
                style = MaterialTheme.typography.labelMedium,
                color = PlumMuted
            )
            Text(
                text = displayName,
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
        Surface(
            onClick = onProfileClick,
            modifier = Modifier.size(48.dp),
            shape = CircleShape,
            color = BlushMist
        ) {
            if (!userPhoto.isNullOrBlank()) {
                Box(modifier = Modifier.fillMaxSize().clip(CircleShape)) {
                    AsyncImage(
                        model = userPhoto,
                        contentDescription = "Open profile",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize()
                    )
                }
            } else {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = initial,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = Rosewood
                    )
                }
            }
        }
    }
}
