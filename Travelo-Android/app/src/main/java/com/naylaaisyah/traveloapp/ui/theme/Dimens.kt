package com.naylaaisyah.traveloapp.ui.theme

import androidx.compose.ui.unit.dp

/**
 * Spacing scale on a 4 dp grid. Use these instead of ad-hoc dp values in
 * new code so layouts stay rhythmically consistent.
 */
object TraveloSpacing {
    val xxs = 4.dp
    val xs = 8.dp
    val sm = 12.dp
    val md = 16.dp
    val lg = 24.dp
    val xl = 32.dp

    /** Horizontal page margin used by most screens. */
    val screen = 20.dp

    /** Extra bottom padding so content clears the floating navigation bar. */
    val bottomBarClearance = 96.dp
}

object TraveloElevation {
    val flat = 0.dp
    val card = 1.dp
    val raised = 2.dp
    val floating = 4.dp
}

/** Minimum accessible touch target, per Material guidance. */
val TouchTarget = 48.dp
