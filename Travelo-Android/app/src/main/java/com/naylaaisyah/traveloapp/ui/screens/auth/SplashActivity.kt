package com.naylaaisyah.traveloapp.ui.screens.auth

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.ui.theme.TraveloAppTheme
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.delay

@AndroidEntryPoint
class SplashActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            TraveloAppTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    SplashContent(
                        onFinished = {
                            startActivity(Intent(this, LoginActivity::class.java))
                            finish()
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun SplashContent(onFinished: () -> Unit) {
    LaunchedEffect(Unit) {
        delay(1500)
        onFinished()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xFF6C63FF)),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Image(
            painter = painterResource(id = R.drawable.ic_travelo_logo),
            contentDescription = "Travelo Logo",
            modifier = Modifier.padding(bottom = 16.dp)
        )
        Text(
            text = "Travelo",
            color = Color.White,
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Plan your trip with ease",
            color = Color.White.copy(alpha = 0.85f),
            fontSize = 14.sp,
            modifier = Modifier.padding(top = 6.dp)
        )
    }
}