package com.naylaaisyah.traveloapp.ui.screens.auth

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
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
                    // This activity is the auth host. Registration is shown in
                    // place so the "Create an account" link is never a no-op.
                    var showRegister by rememberSaveable { mutableStateOf(false) }

                    val openMain = {
                        startActivity(Intent(this, MainActivity::class.java))
                        finish()
                    }

                    if (showRegister) {
                        RegisterScreen(
                            onRegisterSuccess = openMain,
                            onNavigateToLogin = { showRegister = false }
                        )
                    } else {
                        LoginScreen(
                            onLoginSuccess = openMain,
                            onNavigateToRegister = { showRegister = true }
                        )
                    }
                }
            }
        }
    }
}
