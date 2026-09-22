package com.naylaaisyah.traveloapp.ui.screens.tour_package

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.ui.components.ErrorMessage
import com.naylaaisyah.traveloapp.ui.components.LoadingIndicator
import com.naylaaisyah.traveloapp.ui.components.PrimaryButton
import com.naylaaisyah.traveloapp.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TourPackageDetailScreen(
    tourPackageId: Int,
    onBackClick: () -> Unit,
    onBookNowClick: (Int, String, Int) -> Unit,
    viewModel: TourPackageDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(tourPackageId) {
        viewModel.loadTourPackage(tourPackageId)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(uiState.tourPackage?.title ?: "Tour Package") },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface)
            )
        },
        containerColor = Background,
        bottomBar = {
            if (uiState.tourPackage != null) {
                val pkg = uiState.tourPackage!!
                Surface(
                    color = Surface,
                    shadowElevation = 8.dp
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text(
                                text = "Price per person",
                                fontSize = 12.sp,
                                color = Gray500
                            )
                            Text(
                                text = "Rp ${String.format("%,d", pkg.price)}",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = Primary
                            )
                        }
                        Button(
                            onClick = {
                                onBookNowClick(pkg.id, pkg.title, pkg.price)
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Primary),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.height(50.dp)
                        ) {
                            Text(
                                text = "Book Now",
                                color = Color.White,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }
        }
    ) { paddingValues ->
        when {
            uiState.isLoading -> LoadingIndicator()
            uiState.error != null -> ErrorMessage(
                message = uiState.error!!,
                onRetry = { viewModel.loadTourPackage(tourPackageId) }
            )
            uiState.tourPackage != null -> {
                val pkg = uiState.tourPackage!!
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .verticalScroll(rememberScrollState())
                ) {
                    AsyncImage(
                        model = pkg.image,
                        contentDescription = pkg.title,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(250.dp),
                        contentScale = ContentScale.Crop
                    )

                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = pkg.title,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gray900
                        )

                        Spacer(modifier = Modifier.height(8.dp))

                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                Icons.Default.Star,
                                contentDescription = null,
                                tint = Warning,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = String.format("%.1f", pkg.rating ?: 0.0),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gray700
                            )
                            Spacer(modifier = Modifier.width(16.dp))
                            Text(
                                text = "${pkg.durationDays} Days",
                                fontSize = 14.sp,
                                color = Gray500
                            )
                        }

                        Spacer(modifier = Modifier.height(8.dp))

                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                Icons.Default.Star,
                                contentDescription = null,
                                tint = PrimaryLight,
                                modifier = Modifier.size(14.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = "Max ${pkg.maxPeople} people",
                                fontSize = 13.sp,
                                color = Gray500
                            )
                        }

                        Spacer(modifier = Modifier.height(20.dp))

                        Text(
                            text = "Description",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gray900
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = pkg.description.orEmpty(),
                            fontSize = 14.sp,
                            color = Gray600,
                            lineHeight = 22.sp
                        )

                        Spacer(modifier = Modifier.height(20.dp))

                        Text(
                            text = "Destination",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gray900
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        pkg.destination?.let { dest ->
                            Card(
                                colors = CardDefaults.cardColors(containerColor = Surface),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Row(
                                    modifier = Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    AsyncImage(
                                        model = dest.image,
                                        contentDescription = dest.name,
                                        modifier = Modifier
                                            .size(64.dp)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(Gray200),
                                        contentScale = ContentScale.Crop
                                    )
                                    Column(
                                        modifier = Modifier.padding(start = 12.dp)
                                    ) {
                                        Text(
                                            text = dest.name,
                                            fontWeight = FontWeight.Bold,
                                            color = Gray900
                                        )
                                        Text(
                                            text = "${dest.city}, ${dest.country}",
                                            fontSize = 13.sp,
                                            color = Gray500
                                        )
                                    }
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(24.dp))

                        Card(
                            colors = CardDefaults.cardColors(containerColor = Surface),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text(
                                    text = "Included",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gray900
                                )
                                Spacer(modifier = Modifier.height(12.dp))
                                listOf(
                                    "Accommodation",
                                    "Tour Guide",
                                    "Transportation",
                                    "Meals"
                                ).forEach { item ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(vertical = 4.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Icon(
                                            Icons.Default.CheckCircle,
                                            contentDescription = null,
                                            tint = Success,
                                            modifier = Modifier.size(18.dp)
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text(
                                            text = item,
                                            fontSize = 14.sp,
                                            color = Gray600
                                        )
                                    }
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(80.dp))
                    }
                }
            }
        }
    }
}