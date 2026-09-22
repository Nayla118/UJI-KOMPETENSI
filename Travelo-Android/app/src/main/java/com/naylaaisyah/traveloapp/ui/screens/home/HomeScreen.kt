@file:OptIn(ExperimentalMaterial3Api::class)
package com.naylaaisyah.traveloapp.ui.screens.home

import kotlin.OptIn
import androidx.compose.material3.ExperimentalMaterial3Api

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.Explore
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.compose.foundation.Image
import coil.compose.AsyncImage
import androidx.compose.ui.platform.LocalContext
import coil.request.ImageRequest
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.safeCity
import com.naylaaisyah.traveloapp.data.models.safeCountry
import com.naylaaisyah.traveloapp.data.models.safeDescription
import com.naylaaisyah.traveloapp.data.models.safeName
import com.naylaaisyah.traveloapp.data.models.safePrice
import com.naylaaisyah.traveloapp.data.models.safeRating
import com.naylaaisyah.traveloapp.data.models.duration
import com.naylaaisyah.traveloapp.data.models.safeTitle
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.ui.components.*
import com.naylaaisyah.traveloapp.ui.theme.*
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onProfileClick: () -> Unit,
    onMyBookingsClick: () -> Unit,
    onExploreClick: () -> Unit,
    onSearchResultClick: (Int, String) -> Unit,
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    
    // Bottom navigation state
    var selectedTab by remember { mutableIntStateOf(0) }
    
    // Search results dialog state
    var showSearchResults by remember { mutableStateOf(false) }
    
    // Notification snackbar
    val snackbarHostState = remember { SnackbarHostState() }
    
    // Quick book dialog
    var showQuickBookDialog by remember { mutableStateOf(false) }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Background)
    ) {
        Column(
            modifier = Modifier.fillMaxSize()
        ) {
            // Custom Header - TopAppBar
            HomeTopBar(
                userName = uiState.userName,
                userPhoto = uiState.userPhoto,
                onNotificationClick = {
                    // Show notification snackbar
                    CoroutineScope(Dispatchers.Main).launch {
                        snackbarHostState.showSnackbar(
                            message = "No new notifications",
                            duration = SnackbarDuration.Short
                        )
                    }
                }
            )
            
            // Main Content
            if (uiState.isLoading && uiState.popularDestinations.isEmpty()) {
                LoadingIndicator()
            } else if (uiState.error != null && uiState.popularDestinations.isEmpty()) {
                ErrorMessage(
                    message = uiState.error!!,
                    onRetry = { viewModel.refresh() }
                )
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 100.dp)
                ) {
                    // Logo Section
                    item {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(120.dp)
                                .padding(16.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Image(
                                painter = painterResource(id = R.drawable.ic_travelo_logo),
                                contentDescription = "Travelo Logo",
                                modifier = Modifier
                                    .fillMaxHeight()
                                    .aspectRatio(1f),
                                contentScale = ContentScale.Fit
                            )
                        }
                    }
                    
                    // Search Bar
                    item {
                        HomeSearchBar(
                            query = uiState.searchQuery,
                            onQueryChange = { viewModel.updateSearchQuery(it) },
                            onSearchClick = {
                                if (uiState.searchQuery.isNotBlank()) {
                                    showSearchResults = true
                                }
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 24.dp, vertical = 16.dp)
                        )
                    }
                    
                    // Categories Section
                    item {
                        CategoriesSection(
                            onSeeAllClick = { onExploreClick() },
                            onCategoryClick = { category -> 
                                // Navigate to explore with category filter
                                onExploreClick() 
                            },
                            modifier = Modifier.padding(horizontal = 24.dp)
                        )
                    }
                    
                    // Popular Destinations Section
                    item {
                        Spacer(modifier = Modifier.height(24.dp))
                        SectionHeader(
                            title = "Popular Destinations",
                            actionText = "View Maps",
                            onActionClick = { onExploreClick() },
                            modifier = Modifier.padding(horizontal = 24.dp)
                        )
                    }
                    
                    item {
                        Spacer(modifier = Modifier.height(12.dp))
                        PopularDestinationsRow(
                            destinations = uiState.popularDestinations,
                            onDestinationClick = onDestinationClick
                        )
                    }
                    
                    // Featured Tours Section
                    item {
                        Spacer(modifier = Modifier.height(24.dp))
                        SectionHeader(
                            title = "Featured Tours",
                            actionText = "See All",
                            onActionClick = { onExploreClick() },
                            modifier = Modifier.padding(horizontal = 24.dp)
                        )
                    }
                    
                    item {
                        Spacer(modifier = Modifier.height(12.dp))
                        FeaturedToursGrid(
                            tourPackages = uiState.featuredTours,
                            onTourPackageClick = onTourPackageClick,
                            modifier = Modifier.padding(horizontal = 24.dp)
                        )
                    }
                    
                    if (uiState.featuredTours.isEmpty() && !uiState.isLoading) {
                        item {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(32.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "No tour packages available",
                                    style = MaterialTheme.typography.bodyLarge,
                                    color = Gray500
                                )
                            }
                        }
                    }
                }
            }
        }
        
        // Bottom Navigation Bar
        BottomNavigationBar(
            selectedTab = selectedTab,
            onTabSelected = { tab ->
                selectedTab = tab
                when (tab) {
                    0 -> { /* Home - already here */ }
                    1 -> onExploreClick()
                    2 -> onMyBookingsClick()
                    3 -> onProfileClick()
                }
            },
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
        )
        
        // Floating Action Button
        FloatingActionButton(
            onClick = { showQuickBookDialog = true },
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(end = 24.dp, bottom = 120.dp)
                .size(56.dp),
            containerColor = Primary,
            contentColor = Color.White,
            shape = CircleShape
        ) {
            Icon(
                imageVector = Icons.Default.AddLocation,
                contentDescription = "Quick Book"
            )
        }
        
        // Search Results Dialog
        if (showSearchResults) {
            SearchResultsDialog(
                searchQuery = uiState.searchQuery,
                destinations = uiState.popularDestinations,
                tourPackages = uiState.featuredTours,
                onDismiss = { showSearchResults = false },
                onDestinationClick = { destinationId ->
                    showSearchResults = false
                    onDestinationClick(destinationId)
                },
                onTourPackageClick = { tourPackageId ->
                    showSearchResults = false
                    onTourPackageClick(tourPackageId)
                }
            )
        }
        
        // Quick Book Dialog
        if (showQuickBookDialog) {
            QuickBookDialog(
                tourPackages = uiState.featuredTours,
                onDismiss = { showQuickBookDialog = false },
                onTourPackageClick = { tourPackageId ->
                    showQuickBookDialog = false
                    onTourPackageClick(tourPackageId)
                }
            )
        }
        
        // Snackbar Host
        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}

@Composable
private fun HomeTopBar(
    userName: String,
    userPhoto: String?,
    onNotificationClick: () -> Unit
) {
    var profilePhotoError by remember { mutableStateOf(false) }
    
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(Surface)
            .padding(horizontal = 24.dp, vertical = 12.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Left: Profile and Welcome
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Profile Image
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(Gray200),
                    contentAlignment = Alignment.Center
                ) {
                    if (!userPhoto.isNullOrEmpty() && !profilePhotoError) {
                        AsyncImage(
                            model = userPhoto,
                            contentDescription = "Profile",
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop,
                            onError = { profilePhotoError = true }
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Default.Person,
                            contentDescription = "Profile",
                            tint = Gray500,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }
                
                // Welcome Text
                Column {
                    Text(
                        text = "Welcome back,",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500,
                        fontWeight = FontWeight.Medium
                    )
                    Text(
                        text = userName.ifEmpty { "Traveler" },
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = OnSurface
                    )
                }
            }
            
            // Center: App Title
            Text(
                text = "Travelo",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Primary
            )
            
            // Right: Notification Button
            IconButton(
                onClick = onNotificationClick,
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Notifications,
                    contentDescription = "Notifications",
                    tint = OnSurface,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

@Composable
private fun HomeSearchBar(
    query: String,
    onQueryChange: (String) -> Unit,
    onSearchClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .shadow(
                elevation = 4.dp,
                shape = RoundedCornerShape(24.dp),
                spotColor = Color.Black.copy(alpha = 0.05f)
            )
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(24.dp))
                .background(Surface)
                .padding(horizontal = 4.dp, vertical = 4.dp)
        ) {
            // Search Icon
            Icon(
                imageVector = Icons.Default.Search,
                contentDescription = "Search",
                tint = Gray400,
                modifier = Modifier
                    .padding(start = 12.dp)
                    .size(24.dp)
            )
            
            // Text Field
            TextField(
                value = query,
                onValueChange = onQueryChange,
                modifier = Modifier
                    .weight(1f)
                    .padding(horizontal = 8.dp),
                placeholder = {
                    Text(
                        text = "Where to next?",
                        style = MaterialTheme.typography.bodyLarge,
                        color = Gray400
                    )
                },
                singleLine = true,
                colors = TextFieldDefaults.colors(
                    focusedContainerColor = Color.Transparent,
                    unfocusedContainerColor = Color.Transparent,
                    focusedIndicatorColor = Color.Transparent,
                    unfocusedIndicatorColor = Color.Transparent
                )
            )
            
            // Search Button
            Button(
                onClick = onSearchClick,
                modifier = Modifier
                    .height(46.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Primary),
                shape = RoundedCornerShape(16.dp),
                contentPadding = PaddingValues(horizontal = 20.dp, vertical = 8.dp)
            ) {
                Text(
                    text = "Search",
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@Composable
private fun SectionHeader(
    title: String,
    actionText: String,
    onActionClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = OnSurface
        )
        TextButton(
            onClick = onActionClick,
            modifier = Modifier.height(32.dp)
        ) {
            Text(
                text = actionText,
                style = MaterialTheme.typography.bodyMedium,
                color = Primary,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Composable
private fun CategoriesSection(
    onSeeAllClick: () -> Unit,
    onCategoryClick: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val categories = listOf(
        CategoryItem("Beach", Icons.Default.BeachAccess, true),
        CategoryItem("Mountain", Icons.Default.Terrain, false),
        CategoryItem("City", Icons.Default.Apartment, false),
        CategoryItem("Forest", Icons.Default.Forest, false),
        CategoryItem("Surfing", Icons.Default.Surfing, false),
        CategoryItem("Safari", Icons.Default.Pets, false)
    )
    
    Column(modifier = modifier) {
        // Section Header
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Categories",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = OnSurface
            )
            TextButton(
                onClick = onSeeAllClick,
                modifier = Modifier.height(32.dp)
            ) {
                Text(
                    text = "See All",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Primary,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Categories Row
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            items(categories) { category ->
                CategoryItemView(
                    category = category,
                    onClick = { onCategoryClick(category.name) }
                )
            }
        }
    }
}

@Composable
private fun CategoryItemView(
    category: CategoryItem,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .width(80.dp)
            .clickable(onClick = onClick)
    ) {
        Box(
            modifier = Modifier
                .size(64.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(Surface)
                .shadow(
                    elevation = 4.dp,
                    shape = RoundedCornerShape(16.dp),
                    spotColor = Color.Black.copy(alpha = 0.05f)
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = category.icon,
                contentDescription = category.name,
                tint = if (category.isActive) Primary else Gray400,
                modifier = Modifier.size(32.dp)
            )
        }
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            text = category.name,
            style = MaterialTheme.typography.bodySmall,
            fontWeight = FontWeight.Bold,
            color = Gray600
        )
    }
}

private data class CategoryItem(
    val name: String,
    val icon: ImageVector,
    val isActive: Boolean
)

@Composable
private fun PopularDestinationsRow(
    destinations: List<Destination>,
    onDestinationClick: (Int) -> Unit
) {
    LazyRow(
        contentPadding = PaddingValues(horizontal = 24.dp),
        horizontalArrangement = Arrangement.spacedBy(24.dp)
    ) {
        items(destinations) { destination ->
            PopularDestinationCard(
                destination = destination,
                onClick = { onDestinationClick(destination.id) }
            )
        }
    }
}

@Composable
private fun PopularDestinationCard(
    destination: Destination,
    onClick: () -> Unit
) {
    val context = LocalContext.current
    val imageUrl = destination.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800"
    var imageError by remember { mutableStateOf(false) }
    
    // Create ImageRequest with explicit configuration to force load
    val imageRequest = ImageRequest.Builder(context)
        .data(imageUrl)
        .crossfade(true)
        .build()
    
    Card(
        onClick = onClick,
        modifier = Modifier
            .width(280.dp)
            .height(400.dp),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            // Background Image or Fallback
            if (!imageError) {
                AsyncImage(
                    model = imageRequest,
                    contentDescription = destination.safeName,
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop,
                    onError = { imageError = true }
                )
            } else {
                // Fallback when image fails to load
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            Brush.verticalGradient(
                                colors = listOf(
                                    Color(0xFF6C63FF),
                                    Color(0xFFB366FF)
                                )
                            )
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Image,
                        contentDescription = "No image",
                        tint = Color.White.copy(alpha = 0.5f),
                        modifier = Modifier.size(64.dp)
                    )
                }
            }
            
            // Gradient Overlay
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                Color.Black.copy(alpha = 0.0f),
                                Color.Black.copy(alpha = 0.3f),
                                Color.Black.copy(alpha = 0.8f)
                            ),
                            startY = 0f,
                            endY = Float.POSITIVE_INFINITY
                        )
                    )
            )
            
            // Favorite Button
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(16.dp)
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Favorite,
                    contentDescription = "Favorite",
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
            }
            
            // Content - Bottom
            Column(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(24.dp)
            ) {
                Text(
                    text = destination.safeCountry,
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = destination.safeName,
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
            }
        }
    }
}

@Composable
private fun FeaturedToursGrid(
    tourPackages: List<TourPackage>,
    onTourPackageClick: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier,
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        tourPackages.chunked(2).forEach { rowPackages ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                rowPackages.forEach { tourPackage ->
                    TourPackageCard(
                        tourPackage = tourPackage,
                        onClick = { onTourPackageClick(tourPackage.id) },
                        modifier = Modifier.weight(1f)
                    )
                }
                // Add empty space if odd number of packages
                if (rowPackages.size == 1) {
                    Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun TourPackageCard(
    tourPackage: TourPackage,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val imageUrl = tourPackage.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400"
    var imageError by remember { mutableStateOf(false) }
    
    // Create ImageRequest with explicit configuration to force load
    val imageRequest = ImageRequest.Builder(context)
        .data(imageUrl)
        .crossfade(true)
        .build()
    
    Card(
        onClick = onClick,
        modifier = modifier
            .fillMaxWidth()
            .height(120.dp),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Image or Fallback
            if (!imageError) {
                AsyncImage(
                    model = imageRequest,
                    contentDescription = tourPackage.safeTitle,
                    modifier = Modifier
                        .width(96.dp)
                        .height(96.dp)
                        .clip(RoundedCornerShape(16.dp)),
                    contentScale = ContentScale.Crop,
                    onError = { imageError = true }
                )
            } else {
                // Fallback box when image fails
                Box(
                    modifier = Modifier
                        .width(96.dp)
                        .height(96.dp)
                        .clip(RoundedCornerShape(16.dp))
                        .background(Color(0xFFE8EAED)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Image,
                        contentDescription = "No image",
                        tint = Color.Gray.copy(alpha = 0.5f),
                        modifier = Modifier.size(40.dp)
                    )
                }
            }
            
            // Content
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 4.dp),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top
                    ) {
                        Text(
                            text = tourPackage.safeTitle,
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Bold,
                            color = OnSurface,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        
                        // Rating
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(2.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Star,
                                contentDescription = "Rating",
                                tint = Color(0xFFFFD700),
                                modifier = Modifier.size(14.dp)
                            )
                            Text(
                                text = tourPackage.safeRating.toString(),
                                style = MaterialTheme.typography.bodySmall,
                                fontWeight = FontWeight.Bold,
                                color = OnSurface
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    // Duration
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Schedule,
                            contentDescription = "Duration",
                            tint = Gray500,
                            modifier = Modifier.size(12.dp)
                        )
                        Text(
                            text = tourPackage.duration,
                            style = MaterialTheme.typography.bodySmall,
                            color = Gray500,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Bottom
                ) {
                    // Price
                    Text(
                        text = "$${String.format("%.0f", tourPackage.safePrice)}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                    
                    // Chevron
                    Icon(
                        imageVector = Icons.Default.ChevronRight,
                        contentDescription = "View",
                        tint = Gray300,
                        modifier = Modifier.size(24.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun BottomNavigationBar(
    selectedTab: Int,
    onTabSelected: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    val navItems = listOf(
        BottomNavItem(0, "Home", Icons.Filled.Home, Icons.Outlined.Home),
        BottomNavItem(1, "Explore", Icons.Outlined.Explore, Icons.Outlined.Explore),
        BottomNavItem(2, "Bookings", Icons.Default.ConfirmationNumber, Icons.Default.ConfirmationNumber),
        BottomNavItem(3, "Profile", Icons.Filled.Person, Icons.Outlined.Person)
    )
    
    Box(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
            .background(Surface)
            .shadow(
                elevation = 16.dp,
                shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp),
                spotColor = Color.Black.copy(alpha = 0.08f)
            )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
            horizontalArrangement = Arrangement.SpaceAround,
            verticalAlignment = Alignment.CenterVertically
        ) {
            navItems.forEach { item ->
                BottomNavItem(
                    item = item,
                    isSelected = selectedTab == item.index,
                    onClick = { onTabSelected(item.index) }
                )
            }
        }
    }
}

@Composable
private fun BottomNavItem(
    item: BottomNavItem,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (isSelected) Primary.copy(alpha = 0.1f) else Color.Transparent)
            .padding(horizontal = 16.dp, vertical = 8.dp)
            .noRippleClickable(onClick = onClick)
    ) {
        Icon(
            imageVector = if (isSelected) item.selectedIcon else item.unselectedIcon,
            contentDescription = item.label,
            tint = if (isSelected) Primary else Gray400,
            modifier = Modifier.size(28.dp)
        )
    }
}

private data class BottomNavItem(
    val index: Int,
    val label: String,
    val selectedIcon: ImageVector,
    val unselectedIcon: ImageVector
)

// Extension function to disable ripple effect
@Composable
private fun Modifier.noRippleClickable(onClick: () -> Unit): Modifier {
    return this.then(
        clickable(
            indication = null,
            interactionSource = remember { MutableInteractionSource() },
            onClick = onClick
        )
    )
}

// Search Results Dialog
@Composable
private fun SearchResultsDialog(
    searchQuery: String,
    destinations: List<Destination>,
    tourPackages: List<TourPackage>,
    onDismiss: () -> Unit,
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit
) {
    // Filter results based on search query
    val filteredDestinations = destinations.filter { 
        it.safeName.contains(searchQuery, ignoreCase = true) ||
        it.safeCity.contains(searchQuery, ignoreCase = true) ||
        it.safeCountry.contains(searchQuery, ignoreCase = true)
    }
    
    val filteredTours = tourPackages.filter {
        it.safeTitle.contains(searchQuery, ignoreCase = true) ||
        it.safeDescription.contains(searchQuery, ignoreCase = true)
    }
    
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.8f),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Surface)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
            ) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Search Results",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Close"
                        )
                    }
                }
                
                Text(
                    text = "Showing results for \"$searchQuery\"",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Gray500
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Destinations section
                    if (filteredDestinations.isNotEmpty()) {
                        item {
                            Text(
                                text = "Destinations",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        
                        items(filteredDestinations) { destination ->
                            SearchResultDestinationItem(
                                destination = destination,
                                onClick = { onDestinationClick(destination.id) }
                            )
                        }
                    }
                    
                    // Tours section
                    if (filteredTours.isNotEmpty()) {
                        item {
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "Tour Packages",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        
                        items(filteredTours) { tourPackage ->
                            SearchResultTourItem(
                                tourPackage = tourPackage,
                                onClick = { onTourPackageClick(tourPackage.id) }
                            )
                        }
                    }
                    
                    // No results
                    if (filteredDestinations.isEmpty() && filteredTours.isEmpty()) {
                        item {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(32.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    Icon(
                                        imageVector = Icons.Default.SearchOff,
                                        contentDescription = null,
                                        tint = Gray400,
                                        modifier = Modifier.size(48.dp)
                                    )
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        text = "No results found",
                                        style = MaterialTheme.typography.bodyLarge,
                                        color = Gray500
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SearchResultDestinationItem(
    destination: Destination,
    onClick: () -> Unit
) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            AsyncImage(
                model = destination.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=100",
                contentDescription = destination.safeName,
                modifier = Modifier
                    .size(60.dp)
                    .clip(RoundedCornerShape(8.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = destination.safeName,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${destination.safeCity}, ${destination.safeCountry}",
                    style = MaterialTheme.typography.bodySmall,
                    color = Gray500
                )
            }
            
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = Gray400
            )
        }
    }
}

@Composable
private fun SearchResultTourItem(
    tourPackage: TourPackage,
    onClick: () -> Unit
) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            AsyncImage(
                model = tourPackage.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=100",
                contentDescription = tourPackage.safeTitle,
                modifier = Modifier
                    .size(60.dp)
                    .clip(RoundedCornerShape(8.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = tourPackage.safeTitle,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = tourPackage.duration,
                    style = MaterialTheme.typography.bodySmall,
                    color = Gray500
                )
                Text(
                    text = "${String.format("%.0f", tourPackage.safePrice)}",
                    style = MaterialTheme.typography.bodySmall,
                    fontWeight = FontWeight.Bold,
                    color = Primary
                )
            }
            
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = Gray400
            )
        }
    }
}

// Quick Book Dialog
@Composable
private fun QuickBookDialog(
    tourPackages: List<TourPackage>,
    onDismiss: () -> Unit,
    onTourPackageClick: (Int) -> Unit
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.7f),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Surface)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
            ) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.AddLocation,
                            contentDescription = null,
                            tint = Primary
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Quick Book",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Close"
                        )
                    }
                }
                
                Text(
                    text = "Choose a tour to book quickly",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Gray500
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                if (tourPackages.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(32.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(
                                imageVector = Icons.Default.ConfirmationNumber,
                                contentDescription = null,
                                tint = Gray400,
                                modifier = Modifier.size(48.dp)
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "No tours available",
                                style = MaterialTheme.typography.bodyLarge,
                                color = Gray500
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        items(tourPackages) { tourPackage ->
                            QuickBookItem(
                                tourPackage = tourPackage,
                                onClick = { onTourPackageClick(tourPackage.id) }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun QuickBookItem(
    tourPackage: TourPackage,
    onClick: () -> Unit
) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            AsyncImage(
                model = tourPackage.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=100",
                contentDescription = tourPackage.safeTitle,
                modifier = Modifier
                    .size(80.dp)
                    .clip(RoundedCornerShape(12.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        text = tourPackage.safeTitle,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.Schedule,
                            contentDescription = null,
                            tint = Gray500,
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = tourPackage.duration,
                            style = MaterialTheme.typography.bodySmall,
                            color = Gray500
                        )
                    }
                }
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Bottom
                ) {
                    Text(
                        text = "${String.format("%.0f", tourPackage.safePrice)}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                    Button(
                        onClick = onClick,
                        modifier = Modifier.height(32.dp),
                        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 4.dp),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text(
                            text = "Book",
                            style = MaterialTheme.typography.labelMedium
                        )
                    }
                }
            }
        }
    }
}

