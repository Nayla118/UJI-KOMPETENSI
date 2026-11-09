package com.naylaaisyah.traveloapp

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.lifecycleScope
import androidx.navigation.compose.rememberNavController
import com.naylaaisyah.traveloapp.notifications.DeepLinkController
import com.naylaaisyah.traveloapp.notifications.PushRegistrar
import com.naylaaisyah.traveloapp.ui.ConnectivityViewModel
import com.naylaaisyah.traveloapp.ui.components.TraveloOfflineBanner
import com.naylaaisyah.traveloapp.ui.navigation.NavGraph
import com.naylaaisyah.traveloapp.ui.navigation.Screen
import com.naylaaisyah.traveloapp.ui.screens.auth.LoginActivity
import com.naylaaisyah.traveloapp.ui.theme.TraveloAppTheme
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.flow.collect
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    @Inject
    lateinit var sessionManager: SessionManager

    @Inject
    lateinit var pushRegistrar: PushRegistrar

    @Inject
    lateinit var deepLinkController: DeepLinkController

    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { /* Push is optional; either answer is fine. */ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Cold start from a notification: keep the destination until the
        // NavHost (and, if necessary, login) is ready. A configuration change
        // or process recreation must not replay a target that already ran.
        val deepLinkAlreadyHandled =
            savedInstanceState?.getBoolean(KEY_DEEP_LINK_HANDLED, false) ?: false
        if (!deepLinkAlreadyHandled) {
            deepLinkController.handleIntent(intent)
        }

        // Refresh the FCM registration whenever an authenticated app session
        // starts (token rotation, reinstall, or a previous failed attempt).
        if (sessionManager.isLoggedIn()) {
            lifecycleScope.launch {
                pushRegistrar.syncIfSignedIn()
            }
            requestNotificationPermissionIfNeeded()
        }

        setContent {
            TraveloAppTheme {
                val connectivityViewModel: ConnectivityViewModel = hiltViewModel()
                val isOnline by connectivityViewModel.isOnline.collectAsState()
                val pendingDeepLink by deepLinkController.pending.collectAsState()
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
                                pendingDeepLink = pendingDeepLink,
                                onDeepLinkConsumed = deepLinkController::consume,
                                onLogout = ::returnToLogin
                            )
                        }
                    }
                }
            }
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        // A null pending target means the notification has been consumed (or
        // there never was one), so the Intent must not be parsed again.
        outState.putBoolean(KEY_DEEP_LINK_HANDLED, deepLinkController.pending.value == null)
    }

    /**
     * Warm start from a notification. MainActivity is `singleTop`, so an
     * existing task receives the new extras here instead of creating a second
     * activity and a duplicated back stack.
     */
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        deepLinkController.handleIntent(intent)
    }

    /**
     * Android 13+ requires an explicit grant before notifications can be
     * displayed. Ask once per launch; a denial leaves the core flows intact.
     */
    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return

        if (ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.POST_NOTIFICATIONS
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
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

    private companion object {
        const val KEY_DEEP_LINK_HANDLED = "deep_link_handled"
    }
}
