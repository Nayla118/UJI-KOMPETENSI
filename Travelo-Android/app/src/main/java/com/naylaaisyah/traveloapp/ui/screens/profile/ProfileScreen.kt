package com.naylaaisyah.traveloapp.ui.screens.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.ui.screens.auth.AuthViewModel
import com.naylaaisyah.traveloapp.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(
    onBackClick: () -> Unit,
    onLogout: () -> Unit,
    viewModel: ProfileViewModel = hiltViewModel(),
    authViewModel: AuthViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = "Profile",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back"
                        )
                    }
                },
                actions = {
                    IconButton(onClick = { /* Settings */ }) {
                        Icon(
                            imageVector = Icons.Default.Settings,
                            contentDescription = "Settings"
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Surface
                )
            )
        },
        containerColor = Background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
        ) {
            // User Profile Header
            ProfileHeader(
                userName = uiState.userName ?: "Traveler",
                userEmail = uiState.userEmail ?: "user@example.com",
                userPhoto = uiState.userPhoto,
                modifier = Modifier.fillMaxWidth()
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Menu Sections
            Column(
                modifier = Modifier.padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(24.dp)
            ) {
                // Account Section
                ProfileMenuSection(
                    title = "Account",
                    items = listOf(
                        ProfileMenuItem(
                            icon = Icons.Default.Person,
                            title = "Personal Information",
                            onClick = { }
                        ),
                        ProfileMenuItem(
                            icon = Icons.Default.Shield,
                            title = "Security",
                            onClick = { }
                        ),
                        ProfileMenuItem(
                            icon = Icons.Default.Notifications,
                            title = "Notifications",
                            showBadge = true,
                            onClick = { }
                        )
                    )
                )
                
                // Travel Section
                ProfileMenuSection(
                    title = "Travel",
                    items = listOf(
                        ProfileMenuItem(
                            icon = Icons.Default.ConfirmationNumber,
                            title = "My Bookings",
                            onClick = { /* Navigate to bookings */ }
                        ),
                        ProfileMenuItem(
                            icon = Icons.Default.Favorite,
                            title = "Saved Destinations",
                            onClick = { }
                        ),
                        ProfileMenuItem(
                            icon = Icons.Default.Payments,
                            title = "Payment Methods",
                            onClick = { }
                        )
                    )
                )
                
                // Support Section
                ProfileMenuSection(
                    title = "Support",
                    items = listOf(
                        ProfileMenuItem(
                            icon = Icons.Default.Help,
                            title = "Help Center",
                            onClick = { }
                        ),
                        ProfileMenuItem(
                            icon = Icons.Default.Description,
                            title = "Terms of Service",
                            onClick = { }
                        )
                    )
                )
                
                // Logout Button
                Button(
                    onClick = {
                        authViewModel.logout()
                        onLogout()
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color.Transparent,
                        contentColor = Error
                    ),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.Logout,
                        contentDescription = "Logout",
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Logout",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                }
                
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun ProfileHeader(
    userName: String,
    userEmail: String,
    userPhoto: String?,
    modifier: Modifier = Modifier
) {
    val displayName = userName.ifEmpty { "User" }
    
    Column(
        modifier = modifier
            .background(Surface)
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Profile Image
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(CircleShape)
                .background(Primary.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            if (!userPhoto.isNullOrEmpty()) {
                AsyncImage(
                    model = userPhoto,
                    contentDescription = "Profile Photo",
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else {
                Text(
                    text = displayName.take(2).uppercase(),
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.Bold,
                    color = Primary
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // User Info
        Text(
            text = userName,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = OnSurface
        )
        
        Text(
            text = userEmail,
            style = MaterialTheme.typography.bodyMedium,
            color = Gray500
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Edit Profile Button
        OutlinedButton(
            onClick = { },
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(
                contentColor = Primary
            )
        ) {
            Icon(
                imageVector = Icons.Default.Edit,
                contentDescription = "Edit",
                modifier = Modifier.size(18.dp)
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = "Edit Profile",
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Composable
private fun ProfileMenuSection(
    title: String,
    items: List<ProfileMenuItem>
) {
    Column {
        Text(
            text = title,
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.Bold,
            color = Gray500,
            modifier = Modifier.padding(start = 8.dp, bottom = 8.dp)
        )
        
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Column {
                items.forEachIndexed { index, item ->
                    ProfileMenuItemRow(item = item)
                    if (index < items.size - 1) {
                        Divider(
                            modifier = Modifier.padding(horizontal = 16.dp),
                            color = Gray100
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ProfileMenuItemRow(item: ProfileMenuItem) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = item.onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(Primary.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = item.icon,
                contentDescription = item.title,
                tint = Primary,
                modifier = Modifier.size(20.dp)
            )
        }
        
        Spacer(modifier = Modifier.width(12.dp))
        
        Text(
            text = item.title,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.Medium,
            color = OnSurface,
            modifier = Modifier.weight(1f)
        )
        
        if (item.showBadge) {
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .clip(CircleShape)
                    .background(Primary)
            )
            Spacer(modifier = Modifier.width(8.dp))
        }
        
        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = "Go",
            tint = Gray400,
            modifier = Modifier.size(20.dp)
        )
    }
}

data class ProfileMenuItem(
    val icon: ImageVector,
    val title: String,
    val showBadge: Boolean = false,
    val onClick: () -> Unit
)

