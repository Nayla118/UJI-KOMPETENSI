package com.naylaaisyah.traveloapp.ui.screens.tour_package

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.naylaaisyah.traveloapp.data.models.ItineraryDay
import com.naylaaisyah.traveloapp.data.models.TourPackageDetail
import com.naylaaisyah.traveloapp.data.models.TourPackageImage
import com.naylaaisyah.traveloapp.data.models.WishlistTypes
import com.naylaaisyah.traveloapp.data.wishlist.WishlistKey
import com.naylaaisyah.traveloapp.data.wishlist.WishlistToggleState
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloImage
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.WishlistHeartButton
import com.naylaaisyah.traveloapp.ui.screens.favorites.WishlistEvent
import com.naylaaisyah.traveloapp.ui.screens.favorites.WishlistViewModel
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Tour package detail with the information a traveller needs before booking:
 * a photo gallery, day-by-day itinerary, inclusions/exclusions, meeting point
 * with a Google Maps shortcut, and terms & conditions. Every optional section
 * is hidden when the backend has no data for it.
 */
@Composable
fun TourPackageDetailScreen(
    tourPackageId: Int,
    onBackClick: () -> Unit,
    onBookNowClick: (Int, String, Int, Int) -> Unit,
    onRequireLogin: () -> Unit = {},
    viewModel: TourPackageDetailViewModel = hiltViewModel(),
    wishlistViewModel: WishlistViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val wishlistState by wishlistViewModel.toggleState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(tourPackageId) {
        viewModel.loadTourPackage(tourPackageId)
    }

    LaunchedEffect(wishlistViewModel) {
        wishlistViewModel.events.collect { event ->
            when (event) {
                is WishlistEvent.RequiresLogin -> onRequireLogin()
                is WishlistEvent.Message -> snackbarHostState.showSnackbar(event.text)
            }
        }
    }

    Scaffold(
        containerColor = VanillaCream,
        snackbarHost = { SnackbarHost(snackbarHostState) },
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
                    onBackClick = onBackClick,
                    wishlistState = wishlistState,
                    onToggleWishlist = {
                        wishlistViewModel.toggle(
                            WishlistTypes.TOUR_PACKAGE,
                            tourPackage.id,
                            tourPackage.isWishlisted
                        )
                    }
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
    onBackClick: () -> Unit,
    wishlistState: WishlistToggleState,
    onToggleWishlist: () -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = TraveloSpacing.lg)
    ) {
        item {
            Box {
                if (tourPackage.images.isNotEmpty()) {
                    GalleryCarousel(
                        images = tourPackage.images,
                        fallbackUrl = tourPackage.image,
                        title = tourPackage.title
                    )
                } else {
                    TraveloImage(
                        url = tourPackage.image,
                        contentDescription = tourPackage.title,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(250.dp),
                        shape = RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp)
                    )
                }

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

                WishlistHeartButton(
                    isWishlisted = wishlistState.isWishlisted(
                        WishlistKey(WishlistTypes.TOUR_PACKAGE, tourPackage.id),
                        tourPackage.isWishlisted
                    ),
                    onClick = onToggleWishlist,
                    modifier = Modifier
                        .align(Alignment.TopEnd)
                        .padding(TraveloSpacing.sm)
                )
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

                if (tourPackage.itinerary.isNotEmpty()) {
                    SectionTitle("Itinerary")
                    ItinerarySection(tourPackage.itinerary)
                }

                if (tourPackage.inclusions.isNotEmpty() || tourPackage.exclusions.isNotEmpty()) {
                    SectionTitle("What's included")
                    InclusionsExclusionsSection(
                        inclusions = tourPackage.inclusions,
                        exclusions = tourPackage.exclusions
                    )
                }

                val hasMeetingPoint = !tourPackage.meetingPoint.isNullOrBlank() ||
                    (tourPackage.meetingLatitude != null && tourPackage.meetingLongitude != null)
                if (hasMeetingPoint) {
                    SectionTitle("Meeting point")
                    MeetingPointSection(
                        meetingPoint = tourPackage.meetingPoint,
                        latitude = tourPackage.meetingLatitude,
                        longitude = tourPackage.meetingLongitude
                    )
                }

                if (!tourPackage.terms.isNullOrBlank()) {
                    SectionTitle("Terms & conditions")
                    TraveloCard {
                        Text(
                            text = tourPackage.terms.orEmpty(),
                            style = MaterialTheme.typography.bodyMedium,
                            color = PlumInk,
                            lineHeight = 21.sp
                        )
                    }
                }

                tourPackage.destination?.let { destination ->
                    SectionTitle("Destination")

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

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun GalleryCarousel(
    images: List<TourPackageImage>,
    fallbackUrl: String?,
    title: String
) {
    val pagerState = rememberPagerState(pageCount = { images.size })

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(250.dp)
    ) {
        HorizontalPager(state = pagerState) { page ->
            TraveloImage(
                url = images[page].url ?: fallbackUrl,
                contentDescription = images[page].caption ?: title,
                modifier = Modifier.fillMaxSize(),
                shape = RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp)
            )
        }

        if (images.size > 1) {
            Row(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(bottom = TraveloSpacing.md),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                repeat(images.size) { index ->
                    val selected = pagerState.currentPage == index
                    Box(
                        modifier = Modifier
                            .size(if (selected) 9.dp else 7.dp)
                            .background(
                                color = if (selected) SurfaceWhite else SurfaceWhite.copy(alpha = 0.55f),
                                shape = CircleShape
                            )
                    )
                }
            }
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Spacer(Modifier.height(TraveloSpacing.lg))
    Text(
        text = text,
        style = MaterialTheme.typography.titleLarge,
        color = PlumInk
    )
    Spacer(Modifier.height(TraveloSpacing.xs))
}

@Composable
private fun ItinerarySection(itinerary: List<ItineraryDay>) {
    Column(verticalArrangement = Arrangement.spacedBy(TraveloSpacing.sm)) {
        itinerary.forEach { day ->
            ItineraryDayCard(day)
        }
    }
}

@Composable
private fun ItineraryDayCard(day: ItineraryDay) {
    var expanded by rememberSaveable(day.dayNumber) { mutableStateOf(false) }
    val hasDescription = !day.description.isNullOrBlank()

    TraveloCard(
        onClick = if (hasDescription) {
            { expanded = !expanded }
        } else {
            null
        },
        contentPadding = PaddingValues(0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(TraveloSpacing.md),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                shape = CircleShape,
                color = Rosewood
            ) {
                Text(
                    text = "Day ${day.dayNumber}",
                    style = MaterialTheme.typography.labelMedium,
                    color = SurfaceWhite,
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
                )
            }
            Spacer(Modifier.width(TraveloSpacing.sm))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = day.title,
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                if (!expanded && hasDescription) {
                    Text(
                        text = day.description.orEmpty(),
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.padding(top = 2.dp)
                    )
                }
            }
            if (hasDescription) {
                Icon(
                    imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                    contentDescription = if (expanded) "Collapse day" else "Expand day",
                    tint = Rosewood
                )
            }
        }

        if (hasDescription) {
            AnimatedVisibility(visible = expanded) {
                Column(modifier = Modifier.padding(start = TraveloSpacing.md, end = TraveloSpacing.md, bottom = TraveloSpacing.md)) {
                    Divider(color = PlumBorder)
                    Spacer(Modifier.height(TraveloSpacing.sm))
                    Text(
                        text = day.description.orEmpty(),
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumInk,
                        lineHeight = 21.sp
                    )
                }
            }
        }
    }
}

@Composable
private fun InclusionsExclusionsSection(
    inclusions: List<String>,
    exclusions: List<String>
) {
    TraveloCard {
        if (inclusions.isNotEmpty()) {
            Text(
                text = "Included",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(TraveloSpacing.xs))
            inclusions.forEach { item ->
                BulletRow(text = item, icon = Icons.Default.Check, tint = SageDeep)
            }
        }

        if (inclusions.isNotEmpty() && exclusions.isNotEmpty()) {
            Spacer(Modifier.height(TraveloSpacing.sm))
            Divider(color = PlumBorder)
            Spacer(Modifier.height(TraveloSpacing.sm))
        }

        if (exclusions.isNotEmpty()) {
            Text(
                text = "Not included",
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(TraveloSpacing.xs))
            exclusions.forEach { item ->
                BulletRow(text = item, icon = Icons.Default.Close, tint = DangerDeep)
            }
        }
    }
}

@Composable
private fun BulletRow(text: String, icon: ImageVector, tint: Color) {
    Row(
        modifier = Modifier.padding(vertical = 3.dp),
        verticalAlignment = Alignment.Top
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = tint,
            modifier = Modifier
                .padding(top = 2.dp)
                .size(16.dp)
        )
        Spacer(Modifier.width(TraveloSpacing.xs))
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumInk,
            lineHeight = 20.sp
        )
    }
}

@Composable
private fun MeetingPointSection(
    meetingPoint: String?,
    latitude: Double?,
    longitude: Double?
) {
    val context = LocalContext.current
    val hasCoordinates = latitude != null && longitude != null

    TraveloCard {
        Row(verticalAlignment = Alignment.Top) {
            Icon(
                imageVector = Icons.Default.LocationOn,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.xs))
            Text(
                text = meetingPoint?.takeIf { it.isNotBlank() }
                    ?: "Meeting coordinates are available on the map.",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumInk,
                lineHeight = 21.sp
            )
        }

        Spacer(Modifier.height(TraveloSpacing.md))

        TraveloButton(
            text = "Buka di Google Maps",
            onClick = { openMeetingPointInMaps(context, meetingPoint, latitude, longitude) },
            variant = TraveloButtonVariant.Secondary,
            size = TraveloButtonSize.Medium,
            leadingIcon = Icons.Default.Map
        )

        if (hasCoordinates) {
            Spacer(Modifier.height(TraveloSpacing.xs))
            Text(
                text = String.format(java.util.Locale.US, "%.5f, %.5f", latitude, longitude),
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
        }
    }
}

/**
 * Opens the meeting point with a `geo:` intent. If Google Maps (or any map
 * handler) is unavailable, fall back to the browser Maps URL instead of
 * crashing.
 */
private fun openMeetingPointInMaps(
    context: Context,
    meetingPoint: String?,
    latitude: Double?,
    longitude: Double?
) {
    val label = Uri.encode(meetingPoint?.takeIf { it.isNotBlank() } ?: "Meeting point")
    val geoUri = if (latitude != null && longitude != null) {
        Uri.parse("geo:$latitude,$longitude?q=$latitude,$longitude($label)")
    } else {
        Uri.parse("geo:0,0?q=$label")
    }

    try {
        context.startActivity(Intent(Intent.ACTION_VIEW, geoUri))
    } catch (e: ActivityNotFoundException) {
        val query = if (latitude != null && longitude != null) {
            "$latitude,$longitude"
        } else {
            Uri.encode(meetingPoint.orEmpty())
        }
        val webUri = Uri.parse("https://www.google.com/maps/search/?api=1&query=$query")
        try {
            context.startActivity(Intent(Intent.ACTION_VIEW, webUri))
        } catch (ignored: ActivityNotFoundException) {
            // No map or browser handler installed; nothing else we can do.
        }
    }
}

@Composable
private fun MetaItem(
    icon: ImageVector,
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
