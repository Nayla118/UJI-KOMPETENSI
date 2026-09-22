package com.naylaaisyah.traveloapp.ui.screens.destination

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.LocationOn
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.ui.components.ErrorMessage
import com.naylaaisyah.traveloapp.ui.components.LoadingIndicator
import com.naylaaisyah.traveloapp.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DestinationDetailScreen(
    destinationId: Int,
    onBackClick: () -> Unit,
    onTourPackageClick: (Int) -> Unit,
    viewModel: DestinationDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(destinationId) {
        viewModel.loadDestination(destinationId)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(uiState.destination?.name ?: "Destination") },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface)
            )
        },
        containerColor = Background
    ) { paddingValues ->
        when {
            uiState.isLoading -> LoadingIndicator()
            uiState.error != null -> ErrorMessage(
                message = uiState.error!!,
                onRetry = { viewModel.loadDestination(destinationId) }
            )
            uiState.destination != null -> {
                val destination = uiState.destination!!
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .verticalScroll(rememberScrollState())
                ) {
                    AsyncImage(
                        model = destination.image,
                        contentDescription = destination.name,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(260.dp),
                        contentScale = ContentScale.Crop
                    )

                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    text = destination.name,
                                    fontSize = 24.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gray900
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        Icons.Default.LocationOn,
                                        contentDescription = null,
                                        tint = Gray500,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text(
                                        text = "${destination.city}, ${destination.country}",
                                        fontSize = 14.sp,
                                        color = Gray500
                                    )
                                }
                            }
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = Primary.copy(alpha = 0.1f)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(
                                        Icons.Default.Star,
                                        contentDescription = null,
                                        tint = Warning,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text(
                                        text = String.format("%.1f", destination.rating ?: 0.0),
                                        fontWeight = FontWeight.Bold,
                                        color = Gray700
                                    )
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(16.dp))

                        Text(
                            text = "About",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gray900
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = destination.description.orEmpty(),
                            fontSize = 14.sp,
                            color = Gray600,
                            lineHeight = 22.sp
                        )

                        Spacer(modifier = Modifier.height(24.dp))

                        Text(
                            text = "Tour Packages (${uiState.tourPackages.size})",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gray900
                        )
                        Spacer(modifier = Modifier.height(12.dp))

                        if (uiState.tourPackages.isEmpty()) {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                colors = CardDefaults.cardColors(containerColor = Surface),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Text(
                                    text = "No tour packages available for this destination yet.",
                                    modifier = Modifier.padding(24.dp),
                                    color = Gray500
                                )
                            }
                        } else {
                            uiState.tourPackages.forEach { pkg ->
                                Card(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(bottom = 12.dp)
                                        .clickable { onTourPackageClick(pkg.id) },
                                    colors = CardDefaults.cardColors(containerColor = Surface),
                                    shape = RoundedCornerShape(12.dp),
                                    elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(12.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        AsyncImage(
                                            model = pkg.image,
                                            contentDescription = pkg.title,
                                            modifier = Modifier
                                                .size(80.dp)
                                                .clip(RoundedCornerShape(8.dp))
                                                .background(Gray200),
                                            contentScale = ContentScale.Crop
                                        )
                                        Column(
                                            modifier = Modifier
                                                .weight(1f)
                                                .padding(start = 12.dp)
                                        ) {
                                            Text(
                                                text = pkg.title,
                                                fontSize = 15.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = Gray900,
                                                maxLines = 2,
                                                overflow = TextOverflow.Ellipsis
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                Icon(
                                                    Icons.Default.Star,
                                                    contentDescription = null,
                                                    tint = Warning,
                                                    modifier = Modifier.size(14.dp)
                                                )
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text(
                                                    text = String.format("%.1f", pkg.rating ?: 0.0),
                                                    fontSize = 13.sp,
                                                    color = Gray600
                                                )
                                                Spacer(modifier = Modifier.width(12.dp))
                                                Text(
                                                    text = "${pkg.durationDays} Days",
                                                    fontSize = 12.sp,
                                                    color = Gray500
                                                )
                                            }
                                            Spacer(modifier = Modifier.height(6.dp))
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Text(
                                                    text = "Rp ${String.format("%,d", pkg.price)}",
                                                    fontSize = 16.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = Primary
                                                )
                                                Button(
                                                    onClick = { onTourPackageClick(pkg.id) },
                                                    colors = ButtonDefaults.buttonColors(containerColor = Primary),
                                                    shape = RoundedCornerShape(8.dp),
                                                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 6.dp)
                                                ) {
                                                    Text("Details", color = Color.White, fontSize = 12.sp)
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(16.dp))
                    }
                }
            }
        }
    }
}