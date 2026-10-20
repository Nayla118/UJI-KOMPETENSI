package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material.icons.outlined.WifiOff
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint

/** Animated but quiet skeleton placeholder. */
@Composable
private fun shimmerBrush(): Brush {
    val transition = rememberInfiniteTransition(label = "shimmer")
    val x by transition.animateFloat(
        initialValue = -400f,
        targetValue = 1200f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 1400, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "shimmerX"
    )
    return Brush.linearGradient(
        colors = listOf(BlushMist, SurfaceWhite, BlushMist),
        start = Offset(x, 0f),
        end = Offset(x + 400f, 400f)
    )
}

@Composable
fun TraveloSkeleton(
    modifier: Modifier = Modifier,
    shape: RoundedCornerShape = RoundedCornerShape(12.dp)
) {
    Box(modifier = modifier.background(shimmerBrush(), shape))
}

/**
 * Initial-load skeleton: a hero block plus two list rows.
 * Screens should use this instead of a bare spinner on first load.
 */
@Composable
fun TraveloLoadingState(modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        TraveloSkeleton(
            modifier = Modifier
                .fillMaxWidth()
                .height(180.dp),
            shape = RoundedCornerShape(24.dp)
        )
        TraveloSkeleton(
            modifier = Modifier
                .fillMaxWidth()
                .height(16.dp),
            shape = RoundedCornerShape(8.dp)
        )
        TraveloSkeleton(
            modifier = Modifier
                .fillMaxWidth(0.6f)
                .height(16.dp),
            shape = RoundedCornerShape(8.dp)
        )
        repeat(2) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                TraveloSkeleton(
                    modifier = Modifier.size(72.dp),
                    shape = RoundedCornerShape(16.dp)
                )
                Column(
                    modifier = Modifier.weight(1f),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    TraveloSkeleton(
                        modifier = Modifier
                            .fillMaxWidth(0.8f)
                            .height(14.dp)
                    )
                    TraveloSkeleton(
                        modifier = Modifier
                            .fillMaxWidth(0.45f)
                            .height(14.dp)
                    )
                }
            }
        }
    }
}

@Composable
fun TraveloEmptyState(
    title: String,
    message: String,
    modifier: Modifier = Modifier,
    icon: ImageVector = Icons.Outlined.SearchOff,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
    horizontalPadding: androidx.compose.ui.unit.Dp = 32.dp
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = horizontalPadding, vertical = 40.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Surface(
            shape = RoundedCornerShape(percent = 50),
            color = BlushMist
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.padding(14.dp).size(28.dp)
            )
        }
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            color = PlumInk,
            textAlign = TextAlign.Center
        )
        Text(
            text = message,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumMuted,
            textAlign = TextAlign.Center
        )
        if (actionLabel != null && onAction != null) {
            Spacer(Modifier.height(4.dp))
            TraveloButton(
                text = actionLabel,
                onClick = onAction,
                variant = TraveloButtonVariant.Primary,
                size = TraveloButtonSize.Medium,
                modifier = Modifier.fillMaxWidth(0.7f)
            )
        }
    }
}

@Composable
fun TraveloErrorState(
    message: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    title: String = "Couldn't load this page",
    horizontalPadding: androidx.compose.ui.unit.Dp = 32.dp
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = horizontalPadding, vertical = 40.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Surface(shape = RoundedCornerShape(percent = 50), color = BlushMist) {
            Icon(
                imageVector = Icons.Outlined.CloudOff,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.padding(14.dp).size(28.dp)
            )
        }
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            color = PlumInk,
            textAlign = TextAlign.Center
        )
        Text(
            text = message,
            style = MaterialTheme.typography.bodyMedium,
            color = PlumMuted,
            textAlign = TextAlign.Center
        )
        Spacer(Modifier.height(4.dp))
        TraveloButton(
            text = "Try again",
            onClick = onRetry,
            variant = TraveloButtonVariant.Primary,
            size = TraveloButtonSize.Medium,
            modifier = Modifier.fillMaxWidth(0.7f)
        )
    }
}

/**
 * Persistent, dismiss-free offline notice. Showing connectivity explicitly
 * avoids the "empty screen with no explanation" failure mode.
 */
@Composable
fun TraveloOfflineBanner(
    onRetry: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = WarningTint,
        border = BorderStroke(1.dp, WarningDeep.copy(alpha = 0.18f))
    ) {
        Row(
            modifier = Modifier.padding(start = 16.dp, end = 4.dp, top = 6.dp, bottom = 6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Outlined.WifiOff,
                contentDescription = null,
                tint = WarningDeep,
                modifier = Modifier.size(18.dp)
            )
            Spacer(Modifier.size(10.dp))
            Text(
                text = stringResource(R.string.offline_message),
                style = MaterialTheme.typography.bodySmall,
                color = WarningDeep,
                modifier = Modifier.weight(1f)
            )
            TextButton(onClick = onRetry) {
                Text(
                    text = stringResource(R.string.retry),
                    style = MaterialTheme.typography.labelMedium,
                    color = WarningDeep
                )
            }
        }
    }
}

/** Compact centred spinner, for refresh / inline waits. */
@Composable
fun TraveloInlineLoading(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .padding(16.dp),
        contentAlignment = Alignment.Center
    ) {
        CircularProgressIndicator(
            color = Rosewood,
            strokeWidth = 2.dp,
            modifier = Modifier.size(22.dp)
        )
    }
}
