package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite

/**
 * Small heart button used on cards and detail heroes. It is driven purely by
 * the caller's wishlist state so an optimistic toggle can update instantly.
 */
@Composable
fun WishlistHeartButton(
    isWishlisted: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        onClick = onClick,
        modifier = modifier,
        shape = CircleShape,
        color = SurfaceWhite.copy(alpha = 0.92f)
    ) {
        Icon(
            imageVector = if (isWishlisted) {
                Icons.Filled.Favorite
            } else {
                Icons.Outlined.FavoriteBorder
            },
            contentDescription = if (isWishlisted) {
                "Hapus dari favorit"
            } else {
                "Tambah ke favorit"
            },
            tint = if (isWishlisted) Rosewood else PlumMuted,
            modifier = Modifier
                .padding(8.dp)
                .size(20.dp)
        )
    }
}
