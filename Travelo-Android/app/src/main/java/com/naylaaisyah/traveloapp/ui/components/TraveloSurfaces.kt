package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.Gray100
import com.naylaaisyah.traveloapp.ui.theme.Gray500
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.PriceTextStyle
import com.naylaaisyah.traveloapp.ui.theme.PriceTextStyleCompact
import com.naylaaisyah.traveloapp.ui.theme.RoseTint
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.RosewoodPressed
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.formatRupiah

/**
 * Standard card surface: white, 16 dp radius, hairline border.
 * Shadows are reserved for genuinely floating elements.
 */
@Composable
fun TraveloCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    elevated: Boolean = false,
    contentPadding: PaddingValues = PaddingValues(16.dp),
    content: @Composable ColumnScope.() -> Unit
) {
    val shape = CardShape
    val border = if (elevated) null else BorderStroke(1.dp, PlumBorder)
    val shadow = if (elevated) 2.dp else 0.dp

    if (onClick != null) {
        Surface(
            onClick = onClick,
            modifier = modifier.fillMaxWidth(),
            shape = shape,
            color = SurfaceWhite,
            border = border,
            shadowElevation = shadow
        ) {
            Column(modifier = Modifier.padding(contentPadding), content = content)
        }
    } else {
        Surface(
            modifier = modifier.fillMaxWidth(),
            shape = shape,
            color = SurfaceWhite,
            border = border,
            shadowElevation = shadow
        ) {
            Column(modifier = Modifier.padding(contentPadding), content = content)
        }
    }
}

/** Section title with an optional, quiet "see all" action. */
@Composable
fun TraveloSectionHeader(
    title: String,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            color = PlumInk,
            modifier = Modifier.weight(1f)
        )
        if (actionLabel != null && onAction != null) {
            TextButton(onClick = onAction) {
                Text(
                    text = actionLabel,
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    }
}

/** Selectable filter chip. Rosewood is reserved for the selected state text. */
@Composable
fun TraveloChip(
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        onClick = onClick,
        modifier = modifier,
        shape = PillShape,
        color = if (selected) RoseTint else SurfaceWhite,
        border = BorderStroke(
            width = 1.dp,
            color = if (selected) RosewoodPressed else PlumBorder
        )
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = if (selected) RosewoodPressed else PlumMuted,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 9.dp)
        )
    }
}

enum class TraveloStatusTone { Neutral, Accent, Success, Warning, Danger }

private data class StatusColors(val container: Color, val content: Color)

private fun toneColors(tone: TraveloStatusTone): StatusColors = when (tone) {
    TraveloStatusTone.Neutral -> StatusColors(Gray100, Gray500)
    TraveloStatusTone.Accent -> StatusColors(RoseTint, RosewoodPressed)
    TraveloStatusTone.Success -> StatusColors(SageTint, SageDeep)
    TraveloStatusTone.Warning -> StatusColors(WarningTint, WarningDeep)
    TraveloStatusTone.Danger -> StatusColors(DangerTint, DangerDeep)
}

/**
 * Status badge used for booking and payment states.
 * The colour is always paired with a text label, never colour alone.
 */
@Composable
fun TraveloStatusChip(
    label: String,
    tone: TraveloStatusTone,
    modifier: Modifier = Modifier
) {
    val colors = toneColors(tone)
    Surface(
        modifier = modifier,
        shape = PillShape,
        color = colors.container
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = colors.content,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
        )
    }
}

/**
 * Price display with tabular numerals. Use [emphasized] for the total on a
 * checkout summary; list prices stay in Plum Ink so the CTA keeps priority.
 */
@Composable
fun TraveloPriceText(
    amount: Double,
    modifier: Modifier = Modifier,
    compact: Boolean = false,
    emphasized: Boolean = false,
    suffix: String? = null
) {
    Row(modifier = modifier, verticalAlignment = Alignment.Bottom) {
        Text(
            text = formatRupiah(amount),
            style = if (compact) PriceTextStyleCompact else PriceTextStyle,
            color = if (emphasized) Rosewood else PlumInk
        )
        if (suffix != null) {
            Spacer(Modifier.width(4.dp))
            Text(
                text = suffix,
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted,
                modifier = Modifier.padding(bottom = 2.dp)
            )
        }
    }
}

@Composable
fun TraveloRatingBadge(
    rating: Double?,
    modifier: Modifier = Modifier
) {
    if (rating == null || rating <= 0.0) return
    val value = String.format(java.util.Locale.US, "%.1f", rating)
    Surface(
        modifier = modifier.semantics { contentDescription = "Rating $value dari 5" },
        shape = PillShape,
        color = SurfaceWhite.copy(alpha = 0.94f)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(3.dp)
        ) {
            androidx.compose.material3.Icon(
                imageVector = Icons.Filled.Star,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(13.dp)
            )
            Text(
                text = value,
                style = MaterialTheme.typography.labelMedium,
                color = PlumInk
            )
        }
    }
}
