package com.naylaaisyah.traveloapp

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.compose.rememberNavController
import com.naylaaisyah.traveloapp.ui.ConnectivityViewModel
import com.naylaaisyah.traveloapp.ui.components.TraveloOfflineBanner
import com.naylaaisyah.traveloapp.ui.navigation.NavGraph
import com.naylaaisyah.traveloapp.ui.navigation.Screen
import com.naylaaisyah.traveloapp.ui.screens.auth.LoginActivity
import com.naylaaisyah.traveloapp.ui.theme.TraveloAppTheme
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.flow.collect
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    @Inject
    lateinit var sessionManager: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            TraveloAppTheme {
                val connectivityViewModel: ConnectivityViewModel = hiltViewModel()
                val isOnline by connectivityViewModel.isOnline.collectAsState()
                val navController = rememberNavController()

                LaunchedEffect(Unit) {
                    sessionManager.sessionExpired.collect {
                        returnToLogin()
                    }
                }

                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    Column(modifier = Modifier.fillMaxSize()) {
                        if (!isOnline) {
                            TraveloOfflineBanner(onRetry = connectivityViewModel::refresh)
                        }

                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .weight(1f)
                        ) {
                            NavGraph(
                                navController = navController,
                                startDestination = if (sessionManager.isLoggedIn()) {
                                    Screen.Home.route
                                } else {
                                    Screen.Login.route
                                },
                                onLogout = ::returnToLogin
                            )
                        }
                    }
                }
            }
        }
    }

    /**
     * Logout and expired-session paths both start a clean auth task. This
     * prevents the authenticated back stack (and its cached profile state)
     * from being reachable after the session is gone.
     */
    private fun returnToLogin() {
        if (isFinishing || isDestroyed) return

        // Defense in depth: both explicit logout and an expired session must
        // leave no token behind, even if the caller already cleared it.
        sessionManager.clearSession()

        startActivity(
            Intent(this, LoginActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                    Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        )
        finish()
    }
}
