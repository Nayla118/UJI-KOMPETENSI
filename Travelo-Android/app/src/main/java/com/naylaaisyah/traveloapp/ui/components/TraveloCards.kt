package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.outlined.Image
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil.compose.SubcomposeAsyncImage
import coil.request.ImageRequest
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream

/**
 * Image loader with an honest local fallback. We never invent a destination
 * photo: if the backend has no image (or it fails), a neutral rose panel is
 * shown instead.
 */
@Composable
fun TraveloImage(
    url: String?,
    contentDescription: String?,
    modifier: Modifier = Modifier,
    shape: RoundedCornerShape = CardShape,
    contentScale: ContentScale = ContentScale.Crop
) {
    Box(modifier = modifier.clip(shape)) {
        if (url.isNullOrBlank()) {
            ImageFallback()
        } else {
            SubcomposeAsyncImage(
                model = ImageRequest.Builder(LocalContext.current)
                    .data(url)
                    .crossfade(180)
                    .build(),
                contentDescription = contentDescription,
                contentScale = contentScale,
                modifier = Modifier.fillMaxSize(),
                loading = { ImageLoading() },
                error = { ImageFallback() }
            )
        }
    }
}

/**
 * While a photo is downloading, show the brand panel without the error glyph so
 * a slow connection never looks like a broken image.
 */
@Composable
private fun ImageLoading() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(BlushMist)
    )
}

@Composable
private fun ImageFallback() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(BlushMist),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = Icons.Outlined.Image,
            contentDescription = null,
            tint = Rosewood.copy(alpha = 0.38f),
            modifier = Modifier.size(28.dp)
        )
    }
}

/**
 * Destination card with the name and country over the photo.
 * The scrim exists so the text stays readable on any photograph.
 */
@Composable
fun TraveloDestinationCard(
    destination: Destination,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    // Prefer the city when it adds information; fall back to the country to
    // avoid printing the same word twice on the card.
    val eyebrow = destination.city
        .takeIf { it.isNotBlank() && !it.equals(destination.name, ignoreCase = true) }
        ?: destination.country

    Box(
        modifier = modifier
            .clip(HeroShape)
            .clickable(onClick = onClick, role = Role.Button)
    ) {
        TraveloImage(
            url = destination.image,
            contentDescription = null,
            shape = HeroShape,
            modifier = Modifier.fillMaxSize()
        )
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            Color.Transparent,
                            PlumInk.copy(alpha = 0.12f),
                            PlumInk.copy(alpha = 0.82f)
                        )
                    )
                )
        )
        TraveloRatingBadge(
            rating = destination.rating,
            modifier = Modifier
                .align(Alignment.TopEnd)
                .padding(10.dp)
        )
        Column(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(14.dp)
        ) {
            Text(
                text = eyebrow.uppercase(),
                style = MaterialTheme.typography.labelSmall,
                color = VanillaCream.copy(alpha = 0.85f)
            )
            Text(
                text = destination.name,
                style = MaterialTheme.typography.titleLarge,
                color = VanillaCream,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

/** Grid card for a tour package, driven only by fields the API returns. */
@Composable
fun TraveloTourPackageCard(
    tourPackage: TourPackage,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    TraveloCard(
        modifier = modifier,
        onClick = onClick,
        contentPadding = PaddingValues(0.dp)
    ) {
        Box {
            TraveloImage(
                url = tourPackage.image,
                contentDescription = null,
                shape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(16f / 10f)
            )
            TraveloRatingBadge(
                rating = tourPackage.rating,
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(8.dp)
            )
        }
        Column(modifier = Modifier.padding(12.dp)) {
            Text(
                text = tourPackage.title,
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = "${tourPackage.durationDays} days · max ${tourPackage.maxPeople} people",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted,
                modifier = Modifier.padding(top = 4.dp)
            )
            TraveloPriceText(
                amount = tourPackage.price.toDouble(),
                compact = true,
                suffix = "/person",
                modifier = Modifier.padding(top = 8.dp)
            )
        }
    }
}

/** Horizontal variant for lists. */
@Composable
fun TraveloTourPackageRow(
    tourPackage: TourPackage,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    TraveloCard(
        modifier = modifier,
        onClick = onClick,
        contentPadding = PaddingValues(10.dp)
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            TraveloImage(
                url = tourPackage.image,
                contentDescription = null,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.size(88.dp)
            )
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = tourPackage.title,
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = "${tourPackage.durationDays} days · max ${tourPackage.maxPeople} people",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted,
                    modifier = Modifier.padding(top = 2.dp)
                )
                Row(
                    modifier = Modifier.padding(top = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TraveloPriceText(
                        amount = tourPackage.price.toDouble(),
                        compact = true,
                        suffix = "/person"
                    )
                    if ((tourPackage.rating ?: 0.0) > 0.0) {
                        Spacer(Modifier.width(10.dp))
                        Icon(
                            imageVector = Icons.Filled.Star,
                            contentDescription = null,
                            tint = Rosewood,
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(Modifier.width(3.dp))
                        Text(
                            text = String.format(java.util.Locale.US, "%.1f", tourPackage.rating),
                            style = MaterialTheme.typography.labelMedium,
                            color = Rosewood
                        )
                    }
                }
            }
        }
    }
}
