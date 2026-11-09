package com.naylaaisyah.traveloapp.ui.screens.favorites

import androidx.compose.foundation.layout.Arrangement
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.data.models.WishlistEntry
import com.naylaaisyah.traveloapp.data.models.WishlistTypes
import com.naylaaisyah.traveloapp.data.wishlist.WishlistKey
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloChip
import com.naylaaisyah.traveloapp.ui.components.TraveloEmptyState
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloImage
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.WishlistHeartButton
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * "Favorit Saya": two tabs backed by the wishlist endpoint. An item opens the
 * real destination/package detail; removing it uses the DELETE endpoint.
 */
@Composable
fun FavoritesScreen(
    onBackClick: () -> Unit,
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onRequireLogin: () -> Unit,
    viewModel: WishlistViewModel = hiltViewModel()
) {
    val uiState by viewModel.favorites.collectAsState()
    val wishlistState by viewModel.toggleState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    var selectedTab by rememberSaveable { mutableStateOf(WishlistTypes.DESTINATION) }

    LaunchedEffect(Unit) {
        viewModel.loadFavorites()
    }

    LaunchedEffect(viewModel) {
        viewModel.events.collect { event ->
            when (event) {
                is WishlistEvent.RequiresLogin -> onRequireLogin()
                is WishlistEvent.Message -> snackbarHostState.showSnackbar(event.text)
            }
        }
    }

    val entries = if (selectedTab == WishlistTypes.DESTINATION) {
        uiState.destinationEntries
    } else {
        uiState.packageEntries
    }

    Scaffold(
        containerColor = VanillaCream,
        snackbarHost = { SnackbarHost(snackbarHostState) }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(
                        start = TraveloSpacing.xs,
                        end = TraveloSpacing.screen,
                        top = TraveloSpacing.xs
                    ),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xxs)
            ) {
                TraveloIconButton(
                    icon = Icons.Default.ArrowBack,
                    contentDescription = "Back",
                    onClick = onBackClick
                )
                Text(
                    text = "Favorit Saya",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.sm),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                TraveloChip(
                    label = "Destinasi",
                    selected = selectedTab == WishlistTypes.DESTINATION,
                    onClick = { selectedTab = WishlistTypes.DESTINATION }
                )
                TraveloChip(
                    label = "Paket",
                    selected = selectedTab == WishlistTypes.TOUR_PACKAGE,
                    onClick = { selectedTab = WishlistTypes.TOUR_PACKAGE }
                )
            }

            when {
                uiState.isLoading && uiState.isEmpty -> {
                    TraveloLoadingState(
                        modifier = Modifier.padding(horizontal = TraveloSpacing.screen)
                    )
                }

                uiState.error != null && uiState.isEmpty -> {
                    TraveloErrorState(
                        message = uiState.error.orEmpty(),
                        onRetry = viewModel::loadFavorites,
                        title = "Couldn't load your favorites",
                        horizontalPadding = TraveloSpacing.screen
                    )
                }

                entries.isEmpty() -> {
                    TraveloEmptyState(
                        title = if (selectedTab == WishlistTypes.DESTINATION) {
                            "Belum ada destinasi favorit"
                        } else {
                            "Belum ada paket favorit"
                        },
                        message = "Tap the heart on a destination or tour package to save it here.",
                        icon = Icons.Outlined.FavoriteBorder,
                        horizontalPadding = TraveloSpacing.screen
                    )
                }

                else -> {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(
                            start = TraveloSpacing.screen,
                            end = TraveloSpacing.screen,
                            top = TraveloSpacing.xs,
                            bottom = TraveloSpacing.xl
                        ),
                        verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)
                    ) {
                        items(entries, key = { entry -> entry.id }) { entry ->
                            FavoriteRow(
                                entry = entry,
                                isWishlisted = wishlistState.isWishlisted(
                                    WishlistKey(entry.itemType, entry.itemId),
                                    serverValue = true
                                ),
                                onClick = {
                                    if (entry.itemType == WishlistTypes.DESTINATION) {
                                        onDestinationClick(entry.itemId)
                                    } else {
                                        onTourPackageClick(entry.itemId)
                                    }
                                },
                                onRemove = { viewModel.remove(entry) }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun FavoriteRow(
    entry: WishlistEntry,
    isWishlisted: Boolean,
    onClick: () -> Unit,
    onRemove: () -> Unit
) {
    val item = entry.item
    val isPackage = entry.itemType == WishlistTypes.TOUR_PACKAGE

    TraveloCard(
        onClick = onClick,
        contentPadding = PaddingValues(10.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            TraveloImage(
                url = item?.image,
                contentDescription = item?.name,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.size(88.dp)
            )
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = item?.name ?: "Favorit #${entry.itemId}",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                val location = listOfNotNull(item?.city, item?.country)
                    .filter { it.isNotBlank() }
                    .joinToString(", ")
                if (location.isNotBlank()) {
                    Text(
                        text = location,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted,
                        modifier = Modifier.padding(top = 2.dp)
                    )
                }
                if (isPackage) {
                    val price = item?.priceFormatted
                        ?: item?.price?.let { formatRupiah(it) }
                    if (price != null) {
                        Text(
                            text = price,
                            style = MaterialTheme.typography.titleSmall,
                            color = Rosewood,
                            modifier = Modifier.padding(top = 6.dp)
                        )
                    }
                    item?.durationDays?.let { days ->
                        Text(
                            text = "$days days",
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted
                        )
                    }
                }
                item?.rating?.takeIf { it > 0.0 }?.let { rating ->
                    Row(
                        modifier = Modifier.padding(top = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Star,
                            contentDescription = null,
                            tint = Rosewood,
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(Modifier.width(3.dp))
                        Text(
                            text = String.format(java.util.Locale.US, "%.1f", rating),
                            style = MaterialTheme.typography.labelMedium,
                            color = Rosewood
                        )
                    }
                }
            }
            WishlistHeartButton(
                isWishlisted = isWishlisted,
                onClick = onRemove,
                modifier = Modifier.padding(start = 4.dp)
            )
        }
    }
}
