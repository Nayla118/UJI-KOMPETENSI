package com.naylaaisyah.traveloapp.ui.screens.auth

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.material3.MaterialTheme
import androidx.compose.ui.Modifier
import androidx.navigation.compose.rememberNavController
import com.naylaaisyah.traveloapp.MainActivity
import com.naylaaisyah.traveloapp.ui.theme.TraveloAppTheme
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class LoginActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            TraveloAppTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    LoginScreen(
                        onLoginSuccess = {
                            // Navigate to MainActivity
                            val intent = android.content.Intent(this, MainActivity::class.java)
                            startActivity(intent)
                            finish()
                        },
                        onNavigateToRegister = {
                            // Handle navigation to registration
                        }
                    )
                }
            }
        }
    }
}
