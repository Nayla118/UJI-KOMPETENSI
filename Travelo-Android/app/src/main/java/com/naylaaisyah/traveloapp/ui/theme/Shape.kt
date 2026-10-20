package com.naylaaisyah.traveloapp.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Shapes
import androidx.compose.ui.unit.dp

/**
 * Corner radii are limited to four values: 12 / 16 / 20 / 24.
 * Pills use [PillShape].
 */
val TraveloShapes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(20.dp),
    extraLarge = RoundedCornerShape(24.dp)
)

val CardShape = RoundedCornerShape(16.dp)
val HeroShape = RoundedCornerShape(24.dp)
val SheetShape = RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)
val FieldShape = RoundedCornerShape(12.dp)
val PillShape = RoundedCornerShape(percent = 50)
