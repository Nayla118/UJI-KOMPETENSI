package com.naylaaisyah.traveloapp.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Explore
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Explore
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.ui.theme.PillShape

data class TraveloTab(
    val route: String,
    val label: String,
    val icon: ImageVector,
    val selectedIcon: ImageVector
)

val TraveloTabs = listOf(
    TraveloTab("home", "Home", Icons.Outlined.Home, Icons.Filled.Home),
    TraveloTab("explore", "Explore", Icons.Outlined.Explore, Icons.Filled.Explore),
    TraveloTab("my_bookings", "Bookings", Icons.Outlined.ConfirmationNumber, Icons.Filled.ConfirmationNumber),
    TraveloTab("profile", "Profile", Icons.Outlined.Person, Icons.Filled.Person)
)

/**
 * Single bottom navigation implementation shared by every tab screen.
 * Labels are always visible: icon-only navigation is a discoverability and
 * accessibility problem, not a design win.
 */
@Composable
fun TraveloBottomNav(
    currentRoute: String?,
    onSelect: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = VanillaCream,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .heightIn(min = 64.dp)
                .selectableGroup(),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            TraveloTabs.forEach { tab ->
                TraveloTabItem(
                    tab = tab,
                    selected = currentRoute == tab.route,
                    onClick = { onSelect(tab.route) }
                )
            }
        }
    }
}

@Composable
private fun RowScope.TraveloTabItem(
    tab: TraveloTab,
    selected: Boolean,
    onClick: () -> Unit
) {
    Column(
        modifier = Modifier
            .weight(1f)
            .heightIn(min = 64.dp)
            .selectable(selected = selected, onClick = onClick, role = Role.Tab),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Surface(
            shape = PillShape,
            color = if (selected) BlushMist else androidx.compose.ui.graphics.Color.Transparent
        ) {
            Icon(
                imageVector = if (selected) tab.selectedIcon else tab.icon,
                contentDescription = null,
                tint = if (selected) Rosewood else PlumMuted,
                modifier = Modifier
                    .padding(horizontal = 14.dp, vertical = 4.dp)
                    .size(20.dp)
            )
        }
        Text(
            text = tab.label,
            style = MaterialTheme.typography.labelSmall,
            color = if (selected) Rosewood else PlumMuted,
            modifier = Modifier.padding(top = 3.dp)
        )
    }
}
