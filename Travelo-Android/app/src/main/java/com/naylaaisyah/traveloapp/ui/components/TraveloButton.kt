package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TouchTarget

enum class TraveloButtonVariant { Primary, Secondary, Ghost }
enum class TraveloButtonSize { Large, Medium }

/**
 * The one button in the app.
 *
 * Primary   — Rosewood fill. Exactly one per screen.
 * Secondary — white fill with a hairline border.
 * Ghost     — text only, for tertiary actions.
 */
@Composable
fun TraveloButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    variant: TraveloButtonVariant = TraveloButtonVariant.Primary,
    size: TraveloButtonSize = TraveloButtonSize.Large,
    enabled: Boolean = true,
    loading: Boolean = false,
    leadingIcon: ImageVector? = null
) {
    val active = enabled && !loading
    val containerColor = when {
        !active -> BlushMist
        variant == TraveloButtonVariant.Primary -> Rosewood
        variant == TraveloButtonVariant.Secondary -> SurfaceWhite
        else -> Color.Transparent
    }
    val contentColor = when {
        !active -> PlumMuted
        variant == TraveloButtonVariant.Primary -> SurfaceWhite
        else -> Rosewood
    }
    val border = if (variant == TraveloButtonVariant.Secondary && active) {
        BorderStroke(1.dp, PlumBorder)
    } else {
        null
    }

    Surface(
        onClick = onClick,
        modifier = modifier
            .fillMaxWidth()
            .height(if (size == TraveloButtonSize.Large) 52.dp else 44.dp),
        enabled = active,
        shape = RoundedCornerShape(16.dp),
        color = containerColor,
        contentColor = contentColor,
        border = border
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 20.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            if (loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    color = contentColor,
                    strokeWidth = 2.dp
                )
                Spacer(Modifier.width(10.dp))
            } else if (leadingIcon != null) {
                Icon(leadingIcon, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
            }
            Text(text = text, style = MaterialTheme.typography.labelLarge)
        }
    }
}

/** 48 dp icon button with a mandatory description for screen readers. */
@Composable
fun TraveloIconButton(
    icon: ImageVector,
    contentDescription: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    tint: Color = Rosewood,
    enabled: Boolean = true
) {
    IconButton(
        onClick = onClick,
        modifier = modifier.size(TouchTarget),
        enabled = enabled
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            tint = tint,
            modifier = Modifier.size(24.dp)
        )
    }
}
