package com.naylaaisyah.traveloapp.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.material3.Typography as MaterialTypography
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val AppTypography = MaterialTypography()

private val LightColorScheme = lightColorScheme(
    primary = Primary,
    onPrimary = OnPrimary,
    primaryContainer = PrimaryLight,
    onPrimaryContainer = PrimaryDark,
    secondary = Secondary,
    onSecondary = OnSecondary,
    secondaryContainer = SecondaryLight,
    onSecondaryContainer = SecondaryDark,
    tertiary = Info,
    onTertiary = OnPrimary,
    background = Background,
    onBackground = OnBackground,
    surface = Surface,
    onSurface = OnSurface,
    surfaceVariant = SurfaceVariant,
    onSurfaceVariant = OnSurfaceVariant,
    error = Error,
    onError = OnPrimary,
    errorContainer = Error,
    onErrorContainer = OnPrimary
)

private val DarkColorScheme = darkColorScheme(
    primary = PrimaryLight,
    onPrimary = PrimaryDark,
    primaryContainer = Primary,
    onPrimaryContainer = OnPrimary,
    secondary = SecondaryLight,
    onSecondary = SecondaryDark,
    secondaryContainer = Secondary,
    onSecondaryContainer = OnSecondary,
    tertiary = Info,
    onTertiary = OnPrimary,
    background = Gray900,
    onBackground = Gray100,
    surface = Gray800,
    onSurface = Gray100,
    surfaceVariant = Gray700,
    onSurfaceVariant = Gray300,
    error = Error,
    onError = OnPrimary,
    errorContainer = Error,
    onErrorContainer = OnPrimary
)

@Composable
fun TraveloTheme(
    darkTheme: Boolean = false, // FORCE LIGHT THEME - Always use light theme for consistency
    // Dynamic color is disabled because it causes inconsistency across devices
    // Material You (Android 12+) changes colors based on wallpaper, which makes the app look different on each device
    dynamicColor: Boolean = false, // DISABLED for consistent branding
    content: @Composable () -> Unit
) {
    val context = LocalContext.current
    
    // Choose color scheme - always use light theme with fixed colors
    val colorScheme = when {
        // Dynamic color disabled for brand consistency
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            // This branch is never reached since dynamicColor = false
            dynamicLightColorScheme(context)
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme // Always use light theme
    }

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            // Set status bar color to primary
            window.statusBarColor = colorScheme.primary.toArgb()
            // Make status bar icons white (light) for better contrast
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = false
            
            // Set navigation bar color to match background
            window.navigationBarColor = Color.White.toArgb()
            // Make navigation bar icons dark for better contrast
            WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = true
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = AppTypography,
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
