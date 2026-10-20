package com.naylaaisyah.traveloapp.ui.theme

import android.app.Activity
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val ColorDarkSage = androidx.compose.ui.graphics.Color(0xFF33553F)
private val ColorDarkDanger = androidx.compose.ui.graphics.Color(0xFF6B1626)
private val ColorOutline = androidx.compose.ui.graphics.Color(0xFFC9B4BE)
private val ColorDarkRose = androidx.compose.ui.graphics.Color(0xFF45202E)
private val ColorDarkRoseContainer = androidx.compose.ui.graphics.Color(0xFF7A3A52)
private val ColorDarkPink = androidx.compose.ui.graphics.Color(0xFFF2C6D4)
private val ColorDarkSageInk = androidx.compose.ui.graphics.Color(0xFF1F3B2C)
private val ColorDarkBackground = androidx.compose.ui.graphics.Color(0xFF201A1D)
private val ColorDarkOn = androidx.compose.ui.graphics.Color(0xFFF2E4E9)
private val ColorDarkSurface = androidx.compose.ui.graphics.Color(0xFF261F23)
private val ColorDarkSurfaceVariant = androidx.compose.ui.graphics.Color(0xFF4A3B42)
private val ColorDarkOnVariant = androidx.compose.ui.graphics.Color(0xFFD8C4CC)
private val ColorDarkOutline = androidx.compose.ui.graphics.Color(0xFF9C8790)
private val ColorDarkError = androidx.compose.ui.graphics.Color(0xFFF2B8C0)
private val ColorDarkErrorInk = androidx.compose.ui.graphics.Color(0xFF56131F)

private val LightColorScheme = lightColorScheme(
    primary = Rosewood,
    onPrimary = OnPrimary,
    primaryContainer = BlushMist,
    onPrimaryContainer = RosewoodPressed,
    secondary = CottonCandy,
    onSecondary = PlumInk,
    secondaryContainer = RoseTint,
    onSecondaryContainer = RosewoodPressed,
    tertiary = SageDeep,
    onTertiary = SurfaceWhite,
    tertiaryContainer = SageTint,
    onTertiaryContainer = ColorDarkSage,
    background = VanillaCream,
    onBackground = PlumInk,
    surface = SurfaceWhite,
    onSurface = PlumInk,
    surfaceVariant = BlushMist,
    onSurfaceVariant = PlumMuted,
    surfaceTint = Rosewood,
    outline = ColorOutline,
    outlineVariant = PlumBorder,
    error = DangerDeep,
    onError = SurfaceWhite,
    errorContainer = DangerTint,
    onErrorContainer = ColorDarkDanger,
    inverseSurface = PlumInk,
    inverseOnSurface = VanillaCream,
    inversePrimary = CottonCandy,
    scrim = androidx.compose.ui.graphics.Color.Black
)

private val DarkColorScheme = darkColorScheme(
    primary = CottonCandy,
    onPrimary = ColorDarkRose,
    primaryContainer = ColorDarkRoseContainer,
    onPrimaryContainer = BlushMist,
    secondary = ColorDarkPink,
    onSecondary = ColorDarkRose,
    secondaryContainer = ColorDarkRoseContainer,
    onSecondaryContainer = BlushMist,
    tertiary = SageMint,
    onTertiary = ColorDarkSageInk,
    tertiaryContainer = ColorDarkSage,
    onTertiaryContainer = SageTint,
    background = ColorDarkBackground,
    onBackground = ColorDarkOn,
    surface = ColorDarkSurface,
    onSurface = ColorDarkOn,
    surfaceVariant = ColorDarkSurfaceVariant,
    onSurfaceVariant = ColorDarkOnVariant,
    outline = ColorDarkOutline,
    outlineVariant = ColorDarkSurfaceVariant,
    error = ColorDarkError,
    onError = ColorDarkErrorInk,
    errorContainer = ColorDarkDanger,
    onErrorContainer = DangerTint
)

// Dark-theme supporting values. The app ships light-only for now, but the
// scheme stays coherent so enabling dark mode later does not require a
// second design pass.

@Composable
fun TraveloTheme(
    darkTheme: Boolean = false, // Light-only for consistent brand presentation
    dynamicColor: Boolean = false, // Brand colours must not follow the wallpaper
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            // Calm, cream status bar with dark icons reads as premium rather
            // than as a coloured Android 4 strip.
            window.statusBarColor = colorScheme.background.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme

            window.navigationBarColor = colorScheme.background.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = TraveloTypography,
        shapes = TraveloShapes,
        content = content
    )
}

@Composable
fun TraveloAppTheme(
    darkTheme: Boolean = false,
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit
) {
    TraveloTheme(
        darkTheme = darkTheme,
        dynamicColor = dynamicColor,
        content = content
    )
}
