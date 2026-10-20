package com.naylaaisyah.traveloapp.ui.screens.auth

import android.app.Activity
import android.util.Log
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.common.api.ApiException
import com.google.android.gms.common.api.CommonStatusCodes
import com.naylaaisyah.traveloapp.ui.theme.Gray400
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    onNavigateToRegister: () -> Unit,
    viewModel: AuthViewModel = hiltViewModel()
) {
    val authState by viewModel.authState.collectAsState()

    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    val passwordFocus = remember { FocusRequester() }
    val keyboard = LocalSoftwareKeyboardController.current
    val error = authState.error

    val googleSignInLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        Log.d("GoogleSignIn", "LoginScreen: GoogleSignIn result code: ${result.resultCode}")
        if (result.resultCode == Activity.RESULT_OK) {
            val data = result.data
            if (data != null) {
                val task = GoogleSignIn.getSignedInAccountFromIntent(data)
                try {
                    val googleAccount = task.getResult(ApiException::class.java)
                    val idToken = googleAccount.idToken
                    if (idToken != null) {
                        Log.d("GoogleSignIn", "LoginScreen: Starting Firebase login with Google idToken")
                        viewModel.loginWithGoogle(idToken)
                    } else {
                        viewModel.handleGoogleSignInError("Failed to get ID token. Please try again.")
                    }
                } catch (e: ApiException) {
                    Log.e("GoogleSignIn", "LoginScreen: ApiException - ${e.statusCode}", e)
                    val errorMessage = when (e.statusCode) {
                        CommonStatusCodes.CANCELED -> "No Google account found. Please add a Google account in Settings, or sign up with Email/Password below."
                        CommonStatusCodes.NETWORK_ERROR -> "Network error. Please check your connection."
                        CommonStatusCodes.SIGN_IN_REQUIRED -> "Please sign in with Google"
                        CommonStatusCodes.DEVELOPER_ERROR -> "Google sign in error. Try Email/Password signup instead."
                        CommonStatusCodes.INVALID_ACCOUNT -> "Selected Google account is invalid."
                        else -> "Google sign in failed (${e.statusCode}). Try Email/Password instead."
                    }
                    viewModel.handleGoogleSignInError(errorMessage)
                } catch (e: Exception) {
                    Log.e("GoogleSignIn", "LoginScreen: Exception - ${e.message}", e)
                    viewModel.handleGoogleSignInError("An error occurred. Try Email/Password signup instead.")
                }
            }
        } else {
            Log.w("GoogleSignIn", "LoginScreen: Sign in cancelled")
        }
    }

    LaunchedEffect(authState.isAuthenticated) {
        if (authState.isAuthenticated) {
            onLoginSuccess()
        }
    }

    // Dismiss the keyboard so the button/result is never hidden behind it.
    fun submit() {
        keyboard?.hide()
        if (email.isNotBlank() && password.isNotBlank() && !authState.isLoading) {
            viewModel.loginWithEmail(email, password)
        }
    }

    fun googleSignIn() {
        val googleSignInClient = viewModel.getGoogleSignInClient()
        googleSignInClient.signOut().addOnCompleteListener {
            googleSignInLauncher.launch(googleSignInClient.signInIntent)
        }
    }

    // Note: no OTP here — the code is only required at registration
    // (see RegisterScreen). Sign-in goes straight through.
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .verticalScroll(rememberScrollState())
            .imePadding()
    ) {
        AuthBrandBar()

        Spacer(modifier = Modifier.height(8.dp))

        AuthHeader(
            eyebrow = "Travelo account",
            title = "Welcome back",
            subtitle = "Sign in to keep exploring destinations and booking your next trip."
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.screen)
        ) {
            Spacer(modifier = Modifier.height(24.dp))

            AuthErrorBanner(error = error, onDismiss = { viewModel.clearError() })
            if (error != null) Spacer(modifier = Modifier.height(16.dp))

            AuthTextField(
                value = email,
                onValueChange = { email = it },
                label = "Email",
                placeholder = "name@example.com",
                leadingIcon = Icons.Outlined.Email,
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Next,
                onImeAction = {
                    keyboard?.show()
                    passwordFocus.requestFocus()
                },
                enabled = !authState.isLoading
            )

            Spacer(modifier = Modifier.height(16.dp))

            AuthTextField(
                value = password,
                onValueChange = { password = it },
                label = "Password",
                placeholder = "Enter your password",
                leadingIcon = Icons.Outlined.Lock,
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Done,
                onImeAction = { submit() },
                enabled = !authState.isLoading,
                visualTransformation = if (passwordVisible) {
                    VisualTransformation.None
                } else {
                    PasswordVisualTransformation()
                },
                trailingIcon = {
                    IconButton(onClick = { passwordVisible = !passwordVisible }) {
                        Icon(
                            imageVector = if (passwordVisible) {
                                Icons.Filled.VisibilityOff
                            } else {
                                Icons.Filled.Visibility
                            },
                            contentDescription = if (passwordVisible) "Hide password" else "Show password",
                            tint = Gray400
                        )
                    }
                },
                modifier = Modifier.focusRequester(passwordFocus)
            )

            Spacer(modifier = Modifier.height(24.dp))

            AuthPrimaryButton(
                text = "Sign in",
                onClick = { submit() },
                enabled = email.isNotBlank() && password.isNotBlank(),
                isLoading = authState.isLoading
            )

            Spacer(modifier = Modifier.height(22.dp))

            AuthDivider("or continue with")

            Spacer(modifier = Modifier.height(20.dp))

            GoogleAuthButton(onClick = { googleSignIn() }, enabled = !authState.isLoading)

            Spacer(modifier = Modifier.height(20.dp))

            AuthFooterLink(
                prompt = "New to Travelo?",
                actionLabel = "Create an account",
                onClick = onNavigateToRegister
            )

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}
