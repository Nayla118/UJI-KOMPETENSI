package com.naylaaisyah.traveloapp.ui.screens.auth

import android.annotation.SuppressLint
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import com.naylaaisyah.traveloapp.MainActivity
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.ui.theme.TraveloAppTheme
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.delay
import javax.inject.Inject

@SuppressLint("CustomSplashScreen")
@AndroidEntryPoint
class SplashActivity : ComponentActivity() {
    @Inject
    lateinit var sessionManager: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            TraveloAppTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = VanillaCream
                ) {
                    SplashContent(
                        onFinished = {
                            // A stored Sanctum token means the user already has
                            // a backend session. Let the first authenticated API
                            // call decide whether it is still valid; an expired
                            // token is handled centrally by SessionManager.
                            val destination = if (sessionManager.isLoggedIn()) {
                                MainActivity::class.java
                            } else {
                                LoginActivity::class.java
                            }
                            startActivity(Intent(this, destination))
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
    var visible by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        visible = true
        delay(1200)
        onFinished()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(VanillaCream),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        AnimatedVisibility(
            visible = visible,
            enter = fadeIn(animationSpec = tween(durationMillis = 420))
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Image(
                    painter = painterResource(id = R.drawable.travelo_logo),
                    contentDescription = "Travelo",
                    modifier = Modifier.size(176.dp)
                )
                Spacer(modifier = Modifier.height(10.dp))
                Text(
                    text = "Plan your trip with ease",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumMuted
                )
                Spacer(modifier = Modifier.height(20.dp))
                Box(
                    modifier = Modifier
                        .width(44.dp)
                        .height(3.dp)
                        .background(Rosewood, RoundedCornerShape(2.dp))
                )
            }
        }
    }
}
