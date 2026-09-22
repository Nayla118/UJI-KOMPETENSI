package com.naylaaisyah.traveloapp.ui.screens.explore

import android.content.Intent
import androidx.compose.animation.*
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
import androidx.compose.material.icons.outlined.*
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
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

@Composable
fun ExploreScreen(
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onProfileClick: () -> Unit,
    onMyBookingsClick: () -> Unit,
    onHomeClick: () -> Unit,
    viewModel: ExploreViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    
    // Bottom navigation state
    var selectedTab by remember { mutableIntStateOf(1) }
    
    // Filter bottom sheet state
    var showFilterSheet by remember { mutableStateOf(false) }
    
    // Sort dialog state
    var showSortDialog by remember { mutableStateOf(false) }
    
    // Search dialog state
    var showSearchDialog by remember { mutableStateOf(false) }
    
    // Notification snackbar
    val snackbarHostState = remember { SnackbarHostState() }
    
    // Track selected destination for hero section
    var featuredDestination by remember { mutableStateOf<Destination?>(null) }
    
    // Set first destination as featured when data loads
    LaunchedEffect(uiState.destinations) {
        if (uiState.destinations.isNotEmpty() && featuredDestination == null) {
            featuredDestination = uiState.destinations.first()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Background)
    ) {
        Column(
            modifier = Modifier.fillMaxSize()
        ) {
            // Custom Header - Same as Home Screen
            ExploreTopBar(
                userName = uiState.userName,
                userPhoto = uiState.userPhoto,
                onNotificationClick = {
                    CoroutineScope(Dispatchers.Main).launch {
                        snackbarHostState.showSnackbar(
                            message = context.getString(R.string.no_new_notifications),
                            duration = SnackbarDuration.Short
                        )
                    }
                }
            )
            
            // Search and Filter Bar
            SearchFilterBar(
                searchQuery = uiState.searchQuery,
                onSearchQueryChange = { viewModel.updateSearchQuery(it) },
                onSearchClick = { showSearchDialog = true },
                onFilterClick = { showFilterSheet = true },
                onSortClick = { showSortDialog = true },
                onViewToggleClick = { viewModel.toggleViewMode() },
                viewMode = uiState.viewMode,
                selectedCategory = uiState.selectedCategory,
                onCategoryClick = { viewModel.filterByCategory(it) }
            )
            
            // Main Content with simple Box (pull to refresh handled via button)
            Box(modifier = Modifier.fillMaxSize()) {
                if (uiState.isLoading && uiState.destinations.isEmpty()) {
                    ShimmerLoadingContent()
                } else if (uiState.error != null && uiState.destinations.isEmpty()) {
                    ErrorContent(
                        message = uiState.error!!,
                        onRetry = { viewModel.refresh() }
                    )
                } else if (uiState.isSearchActive) {
                    // Search Results Content
                    SearchResultsContent(
                        destinations = uiState.searchResults,
                        tourPackages = uiState.searchTourResults,
                        searchQuery = uiState.searchQuery,
                        onDestinationClick = onDestinationClick,
                        onTourPackageClick = onTourPackageClick,
                        onFavoriteClick = { viewModel.toggleDestinationFavorite(it) },
                        onShareClick = { destination ->
                            val shareIntent = Intent(Intent.ACTION_SEND).apply {
                                type = "text/plain"
                                putExtra(Intent.EXTRA_TEXT, context.getString(
                                    R.string.shareDestination,
                                    destination.safeName,
                                    destination.safeDescription.take(100)
                                ))
                            }
                            context.startActivity(Intent.createChooser(shareIntent, context.getString(R.string.share_via)))
                        },
                        favoriteIds = uiState.favoriteDestinationIds,
                        viewMode = uiState.viewMode
                    )
                } else {
                    // Normal Content
                    NormalContent(
                        featuredDestination = featuredDestination,
                        destinations = uiState.filteredDestinations,
                        tourPackages = uiState.filteredTourPackages,
                        onDestinationClick = onDestinationClick,
                        onTourPackageClick = onTourPackageClick,
                        onExploreClick = { featuredDestination?.let { onDestinationClick(it.id) } },
                        onCollectionClick = { viewModel.filterByCategory(it) },
                        onFavoriteClick = { viewModel.toggleDestinationFavorite(it) },
                        onTourFavoriteClick = { viewModel.toggleTourFavorite(it) },
                        onShareClick = { destination ->
                            val shareIntent = Intent(Intent.ACTION_SEND).apply {
                                type = "text/plain"
                                putExtra(Intent.EXTRA_TEXT, context.getString(
                                    R.string.shareDestination,
                                    destination.safeName,
                                    destination.safeDescription.take(100)
                                ))
                            }
                            context.startActivity(Intent.createChooser(shareIntent, context.getString(R.string.share_via)))
                        },
                        onShareTourClick = { tourPackage ->
                            val shareIntent = Intent(Intent.ACTION_SEND).apply {
                                type = "text/plain"
                                putExtra(Intent.EXTRA_TEXT, context.getString(
                                    R.string.shareTour,
                                    tourPackage.safeTitle,
                                    "$${tourPackage.safePrice.toInt()} - ${tourPackage.duration}"
                                ))
                            }
                            context.startActivity(Intent.createChooser(shareIntent, context.getString(R.string.share_via)))
                        },
                        favoriteDestinationIds = uiState.favoriteDestinationIds,
                        favoriteTourIds = uiState.favoriteTourIds,
                        viewMode = uiState.viewMode,
                        isLoadingMore = uiState.isLoadingMore,
                        hasMorePages = uiState.hasMorePages,
                        onLoadMore = { viewModel.loadMoreContent() },
                        onSeeAllClick = { /* Navigate to see all */ }
                    )
                }
                
                // Floating refresh button
                if (uiState.isRefreshing) {
                    CircularProgressIndicator(
                        modifier = Modifier
                            .align(Alignment.TopCenter)
                            .padding(top = 16.dp)
                            .size(32.dp),
                        color = Primary
                    )
                }
            }
        }
        
        // Snackbar Host
        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
        
        // Bottom Navigation Bar - Same as Home Screen
        BottomNavigationBar(
            selectedTab = selectedTab,
            onTabSelected = { tab ->
                selectedTab = tab
                when (tab) {
                    0 -> onHomeClick()
                    1 -> { /* Already on Explore */ }
                    2 -> onMyBookingsClick()
                    3 -> onProfileClick()
                }
            },
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
        )
        
        // Filter Bottom Sheet
        if (showFilterSheet) {
            FilterBottomSheet(
                currentFilters = uiState.filters,
                onDismiss = { showFilterSheet = false },
                onApply = { filters ->
                    viewModel.applyFilters(filters)
                    showFilterSheet = false
                },
                onReset = {
                    viewModel.resetFilters()
                    showFilterSheet = false
                }
            )
        }
        
        // Sort Dialog
        if (showSortDialog) {
            SortDialog(
                currentSort = uiState.filters.sortBy,
                onDismiss = { showSortDialog = false },
                onSortSelected = { sortOption ->
                    viewModel.setSortOption(sortOption)
                    showSortDialog = false
                }
            )
        }
        
        // Search Dialog
        if (showSearchDialog) {
            SearchDialogContent(
                searchQuery = uiState.searchQuery,
                onSearchQueryChange = { viewModel.updateSearchQuery(it) },
                onDismiss = {
                    showSearchDialog = false
                    viewModel.clearSearch()
                },
                onClear = {
                    viewModel.clearSearch()
                    showSearchDialog = false
                },
                destinations = uiState.searchResults,
                tourPackages = uiState.searchTourResults,
                onDestinationClick = {
                    showSearchDialog = false
                    onDestinationClick(it)
                },
                onTourPackageClick = {
                    showSearchDialog = false
                    onTourPackageClick(it)
                }
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SearchFilterBar(
    searchQuery: String,
    onSearchQueryChange: (String) -> Unit,
    onSearchClick: () -> Unit,
    onFilterClick: () -> Unit,
    onSortClick: () -> Unit,
    onViewToggleClick: () -> Unit,
    viewMode: ViewMode,
    selectedCategory: String?,
    onCategoryClick: (String?) -> Unit
) {
    val categories = listOf(
        stringResource(R.string.category_all) to null,
        stringResource(R.string.category_beach) to "Beach",
        stringResource(R.string.category_mountain) to "Mountain",
        stringResource(R.string.category_city) to "City",
        stringResource(R.string.category_forest) to "Forest"
    )
    
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(Surface)
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        // Search Bar
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .shadow(4.dp, RoundedCornerShape(24.dp))
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(24.dp))
                    .background(Surface)
                    .clickable(onClick = onSearchClick)
                    .padding(horizontal = 16.dp, vertical = 12.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Search,
                    contentDescription = stringResource(R.string.search),
                    tint = Gray400,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = searchQuery.ifEmpty { stringResource(R.string.search_hint) },
                    style = MaterialTheme.typography.bodyLarge,
                    color = if (searchQuery.isEmpty()) Gray400 else OnSurface,
                    modifier = Modifier.weight(1f)
                )
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        // Filter Row
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Filter and Sort Buttons
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Filter Button
                FilterChip(
                    selected = selectedCategory != null,
                    onClick = onFilterClick,
                    label = { Text(stringResource(R.string.filter)) },
                    leadingIcon = {
                        Icon(
                            imageVector = Icons.Default.FilterList,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                )
                
                // Sort Button
                FilterChip(
                    selected = false,
                    onClick = onSortClick,
                    label = { Text(stringResource(R.string.sort_by)) },
                    leadingIcon = {
                        Icon(
                            imageVector = Icons.Default.Sort,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                )
            }
            
            // View Toggle
            IconButton(onClick = onViewToggleClick) {
                Icon(
                    imageVector = if (viewMode == ViewMode.GRID) Icons.Default.ViewList else Icons.Default.GridView,
                    contentDescription = if (viewMode == ViewMode.GRID) stringResource(R.string.view_list) else stringResource(R.string.view_grid),
                    tint = Primary
                )
            }
        }
        
        // Category Chips
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.padding(top = 8.dp)
        ) {
            items(categories) { (label, category) ->
                FilterChip(
                    selected = selectedCategory == category,
                    onClick = { onCategoryClick(category) },
                    label = { Text(label) },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = Primary,
                        selectedLabelColor = Color.White
                    )
                )
            }
        }
    }
}

@Composable
private fun ShimmerLoadingContent() {
    LazyColumn(
        contentPadding = PaddingValues(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            ShimmerBox(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(200.dp)
                    .clip(RoundedCornerShape(16.dp))
            )
        }
        item {
            ShimmerBox(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(24.dp)
                    .width(150.dp)
                    .clip(RoundedCornerShape(4.dp))
            )
        }
        items(5) {
            ShimmerBox(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(120.dp)
                    .clip(RoundedCornerShape(16.dp))
            )
        }
    }
}

@Composable
private fun ShimmerBox(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .background(
                Brush.linearGradient(
                    colors = listOf(
                        Gray200.copy(alpha = 0.6f),
                        Gray200.copy(alpha = 0.2f),
                        Gray200.copy(alpha = 0.6f)
                    )
                )
            )
    )
}

@Composable
private fun ErrorContent(
    message: String,
    onRetry: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Icon(
                imageVector = Icons.Default.Error,
                contentDescription = null,
                tint = com.naylaaisyah.traveloapp.ui.theme.Error,
                modifier = Modifier.size(64.dp)
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = stringResource(R.string.error_occurred),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = Gray500,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(24.dp))
            Button(
                onClick = onRetry,
                colors = ButtonDefaults.buttonColors(containerColor = Primary)
            ) {
                Icon(
                    imageVector = Icons.Default.Refresh,
                    contentDescription = null,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(stringResource(R.string.retry))
            }
        }
    }
}

@Composable
private fun SearchResultsContent(
    destinations: List<Destination>,
    tourPackages: List<TourPackage>,
    searchQuery: String,
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onFavoriteClick: (Int) -> Unit,
    onShareClick: (Destination) -> Unit,
    favoriteIds: Set<Int>,
    viewMode: ViewMode
) {
    if (destinations.isEmpty() && tourPackages.isEmpty()) {
        EmptySearchState(searchQuery = searchQuery)
    } else {
        LazyColumn(
            contentPadding = PaddingValues(bottom = 100.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            if (destinations.isNotEmpty()) {
                item {
                    Text(
                        text = "${stringResource(R.string.destinations)} (${destinations.size})",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp)
                    )
                }
                
                items(destinations) { destination ->
                    SearchDestinationCard(
                        destination = destination,
                        isFavorite = favoriteIds.contains(destination.id),
                        onClick = { onDestinationClick(destination.id) },
                        onFavoriteClick = { onFavoriteClick(destination.id) },
                        onShareClick = { onShareClick(destination) },
                        modifier = Modifier.padding(horizontal = 24.dp)
                    )
                }
            }
            
            if (tourPackages.isNotEmpty()) {
                item {
                    Text(
                        text = "${stringResource(R.string.tour_packages)} (${tourPackages.size})",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp)
                    )
                }
                
                items(tourPackages) { tourPackage ->
                    SearchTourCard(
                        tourPackage = tourPackage,
                        onClick = { onTourPackageClick(tourPackage.id) },
                        modifier = Modifier.padding(horizontal = 24.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun EmptySearchState(searchQuery: String) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Icon(
                imageVector = Icons.Default.SearchOff,
                contentDescription = null,
                tint = Gray400,
                modifier = Modifier.size(80.dp)
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = stringResource(R.string.no_results),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = "\"$searchQuery\"",
                style = MaterialTheme.typography.bodyLarge,
                color = Gray500
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = stringResource(R.string.try_different_search),
                style = MaterialTheme.typography.bodyMedium,
                color = Gray500
            )
        }
    }
}

@Composable
private fun NormalContent(
    featuredDestination: Destination?,
    destinations: List<Destination>,
    tourPackages: List<TourPackage>,
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit,
    onExploreClick: () -> Unit,
    onCollectionClick: (String) -> Unit,
    onFavoriteClick: (Int) -> Unit,
    onTourFavoriteClick: (Int) -> Unit,
    onShareClick: (Destination) -> Unit,
    onShareTourClick: (TourPackage) -> Unit,
    favoriteDestinationIds: Set<Int>,
    favoriteTourIds: Set<Int>,
    viewMode: ViewMode,
    isLoadingMore: Boolean,
    hasMorePages: Boolean,
    onLoadMore: () -> Unit,
    onSeeAllClick: () -> Unit
) {
    LazyColumn(
        contentPadding = PaddingValues(bottom = 100.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Featured Destination
        featuredDestination?.let { destination ->
            item {
                FeaturedEscapeSection(
                    destination = destination,
                    onExploreClick = onExploreClick,
                    modifier = Modifier.padding(vertical = 16.dp)
                )
            }
        }
        
        // Curated Collections
        item {
            CuratedCollectionsSection(
                onCollectionClick = onCollectionClick,
                modifier = Modifier.padding(vertical = 8.dp)
            )
        }
        
        // Trending Now
        item {
            TrendingNowSection(
                destinations = destinations.take(4),
                onDestinationClick = onDestinationClick,
                onSeeAllClick = onSeeAllClick,
                modifier = Modifier.padding(vertical = 8.dp)
            )
        }
        
        // Popular Destinations
        if (destinations.isNotEmpty()) {
            item {
                PopularDestinationsSection(
                    destinations = destinations,
                    onDestinationClick = onDestinationClick,
                    onFavoriteClick = onFavoriteClick,
                    onShareClick = onShareClick,
                    favoriteIds = favoriteDestinationIds,
                    viewMode = viewMode,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }
        }
        
        // Tour Packages Section
        if (tourPackages.isNotEmpty()) {
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp, vertical = 16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = stringResource(R.string.tour_packages),
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    TextButton(onClick = onSeeAllClick) {
                        Text(
                            text = stringResource(R.string.see_all),
                            color = Primary
                        )
                    }
                }
            }
            
            items(tourPackages) { tourPackage ->
                TourPackageListItem(
                    tourPackage = tourPackage,
                    isFavorite = favoriteTourIds.contains(tourPackage.id),
                    onClick = { onTourPackageClick(tourPackage.id) },
                    onFavoriteClick = { onTourFavoriteClick(tourPackage.id) },
                    onShareClick = { onShareTourClick(tourPackage) },
                    modifier = Modifier.padding(horizontal = 24.dp, vertical = 4.dp)
                )
            }
        }
        
        // Load More Indicator
        if (isLoadingMore) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(32.dp),
                        color = Primary
                    )
                }
            }
        }
    }
}

@Composable
private fun FeaturedEscapeSection(
    destination: Destination,
    onExploreClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(400.dp)
            .padding(horizontal = 24.dp)
    ) {
        Card(
            modifier = Modifier.fillMaxSize(),
            shape = RoundedCornerShape(24.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Box(modifier = Modifier.fillMaxSize()) {
                AsyncImage(
                    model = destination.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200",
                    contentDescription = destination.safeName,
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
                
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            Brush.verticalGradient(
                                colors = listOf(
                                    Color.Black.copy(alpha = 0.3f),
                                    Color.Black.copy(alpha = 0.7f)
                                )
                            )
                        )
                )
                
                Column(
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(24.dp)
                ) {
                    Surface(
                        color = Primary,
                        shape = RoundedCornerShape(4.dp)
                    ) {
                        Text(
                            text = stringResource(R.string.featured_escape),
                            style = MaterialTheme.typography.labelSmall,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            letterSpacing = 1.sp,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    Text(
                        text = "The ${destination.safeName}",
                        style = MaterialTheme.typography.displaySmall.copy(
                            fontFamily = androidx.compose.ui.text.font.FontFamily.Serif,
                            fontWeight = FontWeight.Light,
                            fontStyle = FontStyle.Italic
                        ),
                        color = Color.White,
                        lineHeight = 48.sp
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Row(
                        modifier = Modifier.clickable(onClick = onExploreClick),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = stringResource(R.string.explore_now),
                            style = MaterialTheme.typography.labelMedium,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            letterSpacing = 1.sp
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Icon(
                            imageVector = Icons.Default.ArrowForward,
                            contentDescription = null,
                            tint = Color.White,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun CuratedCollectionsSection(
    onCollectionClick: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val collections = listOf(
        Triple(stringResource(R.string.category_beach), "12 ${stringResource(R.string.destinations)}", "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400"),
        Triple(stringResource(R.string.category_mountain), "8 ${stringResource(R.string.destinations)}", "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400"),
        Triple(stringResource(R.string.category_city), "10 ${stringResource(R.string.destinations)}", "https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=400")
    )
    
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 24.dp, vertical = 16.dp)
    ) {
        Text(
            text = stringResource(R.string.curated_collections),
            style = MaterialTheme.typography.headlineMedium.copy(
                fontFamily = androidx.compose.ui.text.font.FontFamily.Serif
            ),
            fontWeight = FontWeight.Normal,
            color = OnSurface
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Collection Cards
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            collections.forEach { (title, subtitle, imageUrl) ->
                CollectionCard(
                    title = title,
                    subtitle = subtitle,
                    imageUrl = imageUrl,
                    onClick = { onCollectionClick(title) },
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
private fun CollectionCard(
    title: String,
    subtitle: String,
    imageUrl: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .aspectRatio(0.8f)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            AsyncImage(
                model = imageUrl,
                contentDescription = title,
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Crop
            )
            
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.4f))
            )
            
            Column(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(12.dp)
            ) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    }
}

@Composable
private fun TrendingNowSection(
    destinations: List<Destination>,
    onDestinationClick: (Int) -> Unit,
    onSeeAllClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = stringResource(R.string.trending_now).uppercase(),
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.Bold,
                color = Gray400,
                letterSpacing = 2.sp
            )
            
            TextButton(onClick = onSeeAllClick) {
                Text(
                    text = stringResource(R.string.view_all),
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.Bold,
                    color = Primary,
                    letterSpacing = 1.sp
                )
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        LazyRow(
            contentPadding = PaddingValues(horizontal = 24.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            items(destinations) { destination ->
                TrendingCard(
                    destination = destination,
                    onClick = { onDestinationClick(destination.id) },
                    modifier = Modifier.width(280.dp)
                )
            }
        }
    }
}

@Composable
private fun TrendingCard(
    destination: Destination,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .height(200.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            AsyncImage(
                model = destination.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400",
                contentDescription = destination.safeName,
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Crop
            )
            
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.3f))
            )
            
            Column(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(16.dp)
            ) {
                Text(
                    text = destination.safeName,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Star,
                        contentDescription = null,
                        tint = Color(0xFFFFD700),
                        modifier = Modifier.size(14.dp)
                    )
                    Text(
                        text = destination.safeRating.toString(),
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White
                    )
                }
            }
        }
    }
}

@Composable
private fun PopularDestinationsSection(
    destinations: List<Destination>,
    onDestinationClick: (Int) -> Unit,
    onFavoriteClick: (Int) -> Unit,
    onShareClick: (Destination) -> Unit,
    favoriteIds: Set<Int>,
    viewMode: ViewMode,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.fillMaxWidth()
    ) {
        Text(
            text = stringResource(R.string.popular_destinations),
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp)
        )
        
        if (viewMode == ViewMode.GRID) {
            LazyRow(
                contentPadding = PaddingValues(horizontal = 24.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                items(destinations) { destination ->
                    PopularDestinationCard(
                        destination = destination,
                        isFavorite = favoriteIds.contains(destination.id),
                        onClick = { onDestinationClick(destination.id) },
                        onFavoriteClick = { onFavoriteClick(destination.id) },
                        onShareClick = { onShareClick(destination) },
                        modifier = Modifier.width(160.dp)
                    )
                }
            }
        } else {
            Column(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.padding(horizontal = 24.dp)
            ) {
                destinations.forEach { destination ->
                    PopularDestinationListItem(
                        destination = destination,
                        isFavorite = favoriteIds.contains(destination.id),
                        onClick = { onDestinationClick(destination.id) },
                        onFavoriteClick = { onFavoriteClick(destination.id) },
                        onShareClick = { onShareClick(destination) }
                    )
                }
            }
        }
    }
}

@Composable
private fun PopularDestinationCard(
    destination: Destination,
    isFavorite: Boolean,
    onClick: () -> Unit,
    onFavoriteClick: () -> Unit,
    onShareClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .height(220.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            AsyncImage(
                model = destination.image ?: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400",
                contentDescription = destination.safeName,
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Crop
            )
            
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                Color.Transparent,
                                Color.Black.copy(alpha = 0.6f)
                            )
                        )
                    )
            )
            
            // Action buttons
            Row(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(8.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                IconButton(
                    onClick = onFavoriteClick,
                    modifier = Modifier
                        .size(32.dp)
                        .background(Color.White.copy(alpha = 0.9f), CircleShape)
                ) {
                    Icon(
                        imageVector = if (isFavorite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                        contentDescription = if (isFavorite) stringResource(R.string.remove_from_favorites) else stringResource(R.string.add_to_favorites),
                        tint = if (isFavorite) com.naylaaisyah.traveloapp.ui.theme.Error else Gray600,
                        modifier = Modifier.size(18.dp)
                    )
                }
                
                IconButton(
                    onClick = onShareClick,
                    modifier = Modifier
                        .size(32.dp)
                        .background(Color.White.copy(alpha = 0.9f), CircleShape)
                ) {
                    Icon(
                        imageVector = Icons.Default.Share,
                        contentDescription = stringResource(R.string.share),
                        tint = Gray600,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
            
            Column(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(12.dp)
            ) {
                Text(
                    text = destination.safeName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Star,
                        contentDescription = null,
                        tint = Color(0xFFFFD700),
                        modifier = Modifier.size(12.dp)
                    )
                    Text(
                        text = destination.safeRating.toString(),
                        style = MaterialTheme.typography.labelSmall,
                        color = Color.White
                    )
                }
            }
        }
    }
}

@Composable
private fun PopularDestinationListItem(
    destination: Destination,
    isFavorite: Boolean,
    onClick: () -> Unit,
    onFavoriteClick: () -> Unit,
    onShareClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
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
                    .size(80.dp)
                    .clip(RoundedCornerShape(8.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        text = destination.safeName,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = "${destination.safeCity}, ${destination.safeCountry}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500
                    )
                }
                
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.Star,
                            contentDescription = null,
                            tint = Color(0xFFFFD700),
                            modifier = Modifier.size(14.dp)
                        )
                        Text(
                            text = destination.safeRating.toString(),
                            style = MaterialTheme.typography.bodySmall
                        )
                    }
                    
                    IconButton(
                        onClick = onFavoriteClick,
                        modifier = Modifier.size(32.dp)
                    ) {
                        Icon(
                            imageVector = if (isFavorite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                            contentDescription = null,
                            tint = if (isFavorite) com.naylaaisyah.traveloapp.ui.theme.Error else Gray400,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    
                    IconButton(
                        onClick = onShareClick,
                        modifier = Modifier.size(32.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Share,
                            contentDescription = null,
                            tint = Gray400,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun SearchDestinationCard(
    destination: Destination,
    isFavorite: Boolean,
    onClick: () -> Unit,
    onFavoriteClick: () -> Unit,
    onShareClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
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
                    .size(80.dp)
                    .clip(RoundedCornerShape(8.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        text = destination.safeName,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = "${destination.safeCity}, ${destination.safeCountry}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500
                    )
                    Text(
                        text = destination.safeDescription,
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Star,
                        contentDescription = null,
                        tint = Color(0xFFFFD700),
                        modifier = Modifier.size(14.dp)
                    )
                    Text(
                        text = destination.safeRating.toString(),
                        style = MaterialTheme.typography.bodySmall
                    )
                    
                    Spacer(modifier = Modifier.weight(1f))
                    
                    IconButton(onClick = onFavoriteClick, modifier = Modifier.size(32.dp)) {
                        Icon(
                            imageVector = if (isFavorite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                            contentDescription = null,
                            tint = if (isFavorite) com.naylaaisyah.traveloapp.ui.theme.Error else Gray400,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    
                    IconButton(onClick = onShareClick, modifier = Modifier.size(32.dp)) {
                        Icon(
                            imageVector = Icons.Default.Share,
                            contentDescription = null,
                            tint = Gray400,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun SearchTourCard(
    tourPackage: TourPackage,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
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
                    .size(80.dp)
                    .clip(RoundedCornerShape(8.dp)),
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
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = tourPackage.duration,
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500
                    )
                }
                
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "$${tourPackage.safePrice.toInt()}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                    
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.Star,
                            contentDescription = null,
                            tint = Color(0xFFFFD700),
                            modifier = Modifier.size(14.dp)
                        )
                        Text(
                            text = tourPackage.safeRating.toString(),
                            style = MaterialTheme.typography.bodySmall
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun TourPackageListItem(
    tourPackage: TourPackage,
    isFavorite: Boolean,
    onClick: () -> Unit,
    onFavoriteClick: () -> Unit,
    onShareClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
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
                    .size(96.dp)
                    .clip(RoundedCornerShape(12.dp)),
                contentScale = ContentScale.Crop
            )
            
            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 4.dp),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = tourPackage.safeTitle,
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Bold,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = Icons.Default.Star,
                                contentDescription = null,
                                tint = Color(0xFFFFD700),
                                modifier = Modifier.size(14.dp)
                            )
                            Text(
                                text = tourPackage.safeRating.toString(),
                                style = MaterialTheme.typography.bodySmall,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.Schedule,
                            contentDescription = null,
                            tint = Gray500,
                            modifier = Modifier.size(12.dp)
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
                        text = "$${tourPackage.safePrice.toInt()}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                    
                    Row {
                        IconButton(onClick = onFavoriteClick, modifier = Modifier.size(32.dp)) {
                            Icon(
                                imageVector = if (isFavorite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                                contentDescription = null,
                                tint = if (isFavorite) com.naylaaisyah.traveloapp.ui.theme.Error else Gray400,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                        
                        IconButton(onClick = onShareClick, modifier = Modifier.size(32.dp)) {
                            Icon(
                                imageVector = Icons.Default.Share,
                                contentDescription = null,
                                tint = Gray400,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                        
                        Icon(
                            imageVector = Icons.Default.ChevronRight,
                            contentDescription = null,
                            tint = Gray300,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }
            }
        }
    }
}

// Bottom Navigation Bar - Same as Home Screen
@Composable
private fun BottomNavigationBar(
    selectedTab: Int,
    onTabSelected: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    val navItems = listOf(
        ExploreBottomNavItem(0, stringResource(R.string.nav_home), Icons.Filled.Home, Icons.Outlined.Home),
        ExploreBottomNavItem(1, stringResource(R.string.nav_explore), Icons.Outlined.Explore, Icons.Outlined.Explore),
        ExploreBottomNavItem(2, stringResource(R.string.nav_bookings), Icons.Default.ConfirmationNumber, Icons.Default.ConfirmationNumber),
        ExploreBottomNavItem(3, stringResource(R.string.nav_profile), Icons.Filled.Person, Icons.Outlined.Person)
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
                ExploreBottomNavItemView(
                    item = item,
                    isSelected = selectedTab == item.index,
                    onClick = { onTabSelected(item.index) }
                )
            }
        }
    }
}

@Composable
private fun ExploreBottomNavItemView(
    item: ExploreBottomNavItem,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (isSelected) Primary.copy(alpha = 0.1f) else Color.Transparent)
            .clickable(
                indication = null,
                interactionSource = remember { MutableInteractionSource() },
                onClick = onClick
            )
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Icon(
            imageVector = if (isSelected) item.selectedIcon else item.unselectedIcon,
            contentDescription = item.label,
            tint = if (isSelected) Primary else Gray400,
            modifier = Modifier.size(28.dp)
        )
    }
}

private data class ExploreBottomNavItem(
    val index: Int,
    val label: String,
    val selectedIcon: ImageVector,
    val unselectedIcon: ImageVector
)

// Filter Bottom Sheet
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FilterBottomSheet(
    currentFilters: ExploreFilters,
    onDismiss: () -> Unit,
    onApply: (ExploreFilters) -> Unit,
    onReset: () -> Unit
) {
    var selectedCategory by remember { mutableStateOf(currentFilters.category) }
    var minPrice by remember { mutableStateOf(currentFilters.minPrice?.toString() ?: "") }
    var maxPrice by remember { mutableStateOf(currentFilters.maxPrice?.toString() ?: "") }
    var minRating by remember { mutableFloatStateOf(currentFilters.minRating ?: 0f) }
    var selectedSort by remember { mutableStateOf(currentFilters.sortBy) }
    
    val sheetState = rememberModalBottomSheetState()
    
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp)
        ) {
            Text(
                text = stringResource(R.string.filters),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Category
            Text(
                text = stringResource(R.string.categories),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Pre-load string resources to avoid composable context issues
            val categoryAll = stringResource(R.string.category_all)
            val categoryBeach = stringResource(R.string.category_beach)
            val categoryMountain = stringResource(R.string.category_mountain)
            val categoryCity = stringResource(R.string.category_city)
            val categoryForest = stringResource(R.string.category_forest)
            
            val categories = listOf(
                categoryAll to null,
                categoryBeach to "Beach",
                categoryMountain to "Mountain",
                categoryCity to "City",
                categoryForest to "Forest"
            )
            
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(categories) { (label, category) ->
                    FilterChip(
                        selected = selectedCategory == category || (selectedCategory == null && category == null),
                        onClick = {
                            selectedCategory = category
                        },
                        label = { Text(label) }
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Price Range
            Text(
                text = stringResource(R.string.price_range),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                OutlinedTextField(
                    value = minPrice,
                    onValueChange = { minPrice = it },
                    label = { Text(stringResource(R.string.min_price)) },
                    modifier = Modifier.weight(1f),
                    prefix = { Text("$") },
                    singleLine = true
                )
                
                OutlinedTextField(
                    value = maxPrice,
                    onValueChange = { maxPrice = it },
                    label = { Text(stringResource(R.string.max_price)) },
                    modifier = Modifier.weight(1f),
                    prefix = { Text("$") },
                    singleLine = true
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Rating Filter
            Text(
                text = stringResource(R.string.rating_filter),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Slider(
                value = minRating,
                onValueChange = { minRating = it },
                valueRange = 0f..5f,
                steps = 9,
                colors = SliderDefaults.colors(
                    thumbColor = Primary,
                    activeTrackColor = Primary
                )
            )
            
            Text(
                text = "${minRating.toInt()}+ ${stringResource(R.string.rating_filter)}",
                style = MaterialTheme.typography.bodySmall,
                color = Gray500
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Sort Options
            Text(
                text = stringResource(R.string.sort_by),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            val sortOptions = listOf(
                SortOption.RATING to stringResource(R.string.sort_rating),
                SortOption.PRICE_LOW_HIGH to stringResource(R.string.sort_price_low_high),
                SortOption.PRICE_HIGH_LOW to stringResource(R.string.sort_price_high_low),
                SortOption.NEWEST to stringResource(R.string.sort_newest)
            )
            
            Column {
                sortOptions.forEach { (sort, label) ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { selectedSort = sort }
                            .padding(vertical = 8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        RadioButton(
                            selected = selectedSort == sort,
                            onClick = { selectedSort = sort },
                            colors = RadioButtonDefaults.colors(selectedColor = Primary)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(label)
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedButton(
                    onClick = onReset,
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Text(stringResource(R.string.reset_filters))
                }
                
                Button(
                    onClick = {
                        onApply(
                            ExploreFilters(
                                category = selectedCategory,
                                minPrice = minPrice.toDoubleOrNull(),
                                maxPrice = maxPrice.toDoubleOrNull(),
                                minRating = if (minRating > 0) minRating else null,
                                sortBy = selectedSort
                            )
                        )
                    },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Primary)
                ) {
                    Text(stringResource(R.string.apply_filters))
                }
            }
            
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

// Sort Dialog
@Composable
private fun SortDialog(
    currentSort: SortOption,
    onDismiss: () -> Unit,
    onSortSelected: (SortOption) -> Unit
) {
    val sortOptions = listOf(
        SortOption.RATING to stringResource(R.string.sort_rating),
        SortOption.PRICE_LOW_HIGH to stringResource(R.string.sort_price_low_high),
        SortOption.PRICE_HIGH_LOW to stringResource(R.string.sort_price_high_low),
        SortOption.NEWEST to stringResource(R.string.sort_newest)
    )
    
    Dialog(onDismissRequest = onDismiss) {
        Card(
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Surface)
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Text(
                    text = stringResource(R.string.sort_by),
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                sortOptions.forEach { (sort, label) ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onSortSelected(sort) }
                            .padding(vertical = 12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        RadioButton(
                            selected = currentSort == sort,
                            onClick = { onSortSelected(sort) },
                            colors = RadioButtonDefaults.colors(selectedColor = Primary)
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(
                            text = label,
                            style = MaterialTheme.typography.bodyLarge
                        )
                    }
                }
                
                TextButton(
                    onClick = onDismiss,
                    modifier = Modifier.align(Alignment.End)
                ) {
                    Text(stringResource(R.string.cancel))
                }
            }
        }
    }
}

// Search Dialog
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SearchDialogContent(
    searchQuery: String,
    onSearchQueryChange: (String) -> Unit,
    onDismiss: () -> Unit,
    onClear: () -> Unit,
    destinations: List<Destination>,
    tourPackages: List<TourPackage>,
    onDestinationClick: (Int) -> Unit,
    onTourPackageClick: (Int) -> Unit
) {
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
                // Search Header
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = onSearchQueryChange,
                    modifier = Modifier.fillMaxWidth(),
                    placeholder = { Text(stringResource(R.string.search_hint)) },
                    leadingIcon = {
                        Icon(Icons.Default.Search, contentDescription = null)
                    },
                    trailingIcon = {
                        if (searchQuery.isNotEmpty()) {
                            IconButton(onClick = onClear) {
                                Icon(Icons.Default.Clear, contentDescription = stringResource(R.string.clear))
                            }
                        }
                    },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp)
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                if (destinations.isEmpty() && tourPackages.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
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
                                text = stringResource(R.string.no_results),
                                style = MaterialTheme.typography.bodyLarge,
                                color = Gray500
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        if (destinations.isNotEmpty()) {
                            item {
                                Text(
                                    text = "${stringResource(R.string.destinations)} (${destinations.size})",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                            
                            items(destinations) { destination ->
                                SearchDestinationCard(
                                    destination = destination,
                                    isFavorite = false,
                                    onClick = { onDestinationClick(destination.id) },
                                    onFavoriteClick = { },
                                    onShareClick = { }
                                )
                            }
                        }
                        
                        if (tourPackages.isNotEmpty()) {
                            item {
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "${stringResource(R.string.tour_packages)} (${tourPackages.size})",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                            
                            items(tourPackages) { tourPackage ->
                                SearchTourCard(
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
}

// Top Bar - Same as Home Screen
@Composable
private fun ExploreTopBar(
    userName: String,
    userPhoto: String?,
    onNotificationClick: () -> Unit
) {
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
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(Gray200),
                    contentAlignment = Alignment.Center
                ) {
                    if (!userPhoto.isNullOrEmpty()) {
                        AsyncImage(
                            model = userPhoto,
                            contentDescription = stringResource(R.string.nav_profile),
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Default.Person,
                            contentDescription = stringResource(R.string.nav_profile),
                            tint = Gray500,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }
                
                Column {
                    Text(
                        text = stringResource(R.string.explore_title),
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500,
                        fontWeight = FontWeight.Medium
                    )
                    Text(
                        text = stringResource(R.string.destinations),
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = OnSurface
                    )
                }
            }
            
            Text(
                text = stringResource(R.string.app_name),
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Primary
            )
            
            IconButton(
                onClick = onNotificationClick,
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Notifications,
                    contentDescription = stringResource(R.string.notifications),
                    tint = OnSurface,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

