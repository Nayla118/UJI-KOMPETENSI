package com.naylaaisyah.traveloapp.ui.screens.auth

import android.app.Activity
import android.util.Log
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.common.api.ApiException
import com.google.android.gms.common.api.CommonStatusCodes
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.ui.theme.Primary
import com.naylaaisyah.traveloapp.ui.theme.PrimaryDark

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
    var showEmailLogin by remember { mutableStateOf(false) }

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

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xFFF6F6F8))
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Hero Section
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(320.dp)
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(Primary, PrimaryDark)
                        )
                    )
            ) {
                // Decorative circles
                Box(
                    modifier = Modifier
                        .size(200.dp)
                        .offset(x = (-40).dp, y = (-40).dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.06f))
                )
                Box(
                    modifier = Modifier
                        .size(150.dp)
                        .offset(x = 260.dp, y = 20.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.06f))
                )
                Box(
                    modifier = Modifier
                        .size(100.dp)
                        .offset(x = 30.dp, y = 220.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.04f))
                )

                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(horizontal = 32.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Spacer(modifier = Modifier.height(24.dp))

                    // Logo
                    Surface(
                        modifier = Modifier.size(72.dp),
                        shape = RoundedCornerShape(20.dp),
                        color = Color.White.copy(alpha = 0.2f)
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier.fillMaxSize()
                        ) {
                            Image(
                                painter = painterResource(id = R.drawable.ic_travelo_logo),
                                contentDescription = "Travelo",
                                modifier = Modifier.size(40.dp)
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(20.dp))

                    Text(
                        text = "Welcome to",
                        style = MaterialTheme.typography.titleLarge,
                        color = Color.White.copy(alpha = 0.85f),
                        fontSize = 18.sp
                    )

                    Text(
                        text = "Travelo",
                        style = MaterialTheme.typography.headlineLarge,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        fontSize = 36.sp
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Text(
                        text = "Discover and book amazing\ntravel experiences",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.White.copy(alpha = 0.75f),
                        textAlign = TextAlign.Center,
                        lineHeight = 22.sp
                    )
                }
            }

            // Login Section
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .offset(y = (-30).dp)
            ) {
                // Google Sign-In Card
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            text = "Sign In",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFF1E293B)
                        )

                        Spacer(modifier = Modifier.height(4.dp))

                        Text(
                            text = "Choose your preferred sign in method",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B)
                        )

                        Spacer(modifier = Modifier.height(24.dp))

                        // Google Sign-In Button
                        OutlinedButton(
                            onClick = {
                                val googleSignInClient = viewModel.getGoogleSignInClient()
                                googleSignInClient.signOut().addOnCompleteListener {
                                    val signInIntent = googleSignInClient.signInIntent
                                    googleSignInLauncher.launch(signInIntent)
                                }
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(56.dp),
                            enabled = !authState.isLoading,
                            shape = RoundedCornerShape(14.dp),
                            border = BorderStroke(1.5.dp, Color(0xFFE5E7EB)),
                            colors = ButtonDefaults.outlinedButtonColors(
                                containerColor = Color.White,
                                contentColor = Color(0xFF1E293B)
                            )
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.Center
                            ) {
                                Image(
                                    painter = painterResource(id = R.drawable.ic_google),
                                    contentDescription = "Google",
                                    modifier = Modifier.size(22.dp)
                                )
                                Spacer(modifier = Modifier.width(12.dp))
                                Text(
                                    text = "Continue with Google",
                                    style = MaterialTheme.typography.titleSmall,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }

                        // Divider
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 20.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Divider(
                                modifier = Modifier.weight(1f),
                                color = Color(0xFFE5E7EB)
                            )
                            Text(
                                text = "or",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color(0xFF94A3B8),
                                modifier = Modifier.padding(horizontal = 12.dp)
                            )
                            Divider(
                                modifier = Modifier.weight(1f),
                                color = Color(0xFFE5E7EB)
                            )
                        }

                        // Email Login Toggle
                        TextButton(
                            onClick = { showEmailLogin = !showEmailLogin },
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text(
                                text = if (showEmailLogin) "Hide email login" else "Sign in with Email",
                                style = MaterialTheme.typography.labelLarge,
                                color = Primary,
                                fontWeight = FontWeight.SemiBold
                            )
                        }

                        // Expandable Email Login
                        AnimatedVisibility(visible = showEmailLogin) {
                            Column(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Spacer(modifier = Modifier.height(16.dp))

                                OutlinedTextField(
                                    value = email,
                                    onValueChange = { email = it },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(56.dp),
                                    placeholder = {
                                        Text("name@example.com", color = Color(0xFF9CA3AF))
                                    },
                                    leadingIcon = {
                                        Text("✉", style = MaterialTheme.typography.titleMedium)
                                    },
                                    keyboardOptions = KeyboardOptions(
                                        keyboardType = KeyboardType.Email,
                                        imeAction = ImeAction.Next
                                    ),
                                    singleLine = true,
                                    shape = RoundedCornerShape(12.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Primary,
                                        unfocusedBorderColor = Color(0xFFE5E7EB),
                                        focusedLeadingIconColor = Primary,
                                        unfocusedLeadingIconColor = Color(0xFF9CA3AF)
                                    )
                                )

                                Spacer(modifier = Modifier.height(12.dp))

                                OutlinedTextField(
                                    value = password,
                                    onValueChange = { password = it },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(56.dp),
                                    placeholder = { Text("Password", color = Color(0xFF9CA3AF)) },
                                    leadingIcon = {
                                        Text("🔒", style = MaterialTheme.typography.titleMedium)
                                    },
                                    trailingIcon = {
                                        TextButton(onClick = { passwordVisible = !passwordVisible }) {
                                            Text(
                                                text = if (passwordVisible) "🙈" else "👁",
                                                style = MaterialTheme.typography.titleMedium
                                            )
                                        }
                                    },
                                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                                    keyboardOptions = KeyboardOptions(
                                        keyboardType = KeyboardType.Password,
                                        imeAction = ImeAction.Done
                                    ),
                                    singleLine = true,
                                    shape = RoundedCornerShape(12.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Primary,
                                        unfocusedBorderColor = Color(0xFFE5E7EB),
                                        focusedLeadingIconColor = Primary,
                                        unfocusedLeadingIconColor = Color(0xFF9CA3AF)
                                    )
                                )

                                Spacer(modifier = Modifier.height(20.dp))

                                Button(
                                    onClick = { viewModel.loginWithEmail(email, password) },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(52.dp),
                                    enabled = email.isNotBlank() && password.isNotBlank() && !authState.isLoading,
                                    shape = RoundedCornerShape(12.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Primary,
                                        disabledContainerColor = Primary.copy(alpha = 0.5f)
                                    )
                                ) {
                                    if (authState.isLoading) {
                                        CircularProgressIndicator(
                                            modifier = Modifier.size(22.dp),
                                            color = Color.White,
                                            strokeWidth = 2.dp
                                        )
                                    } else {
                                        Text(
                                            text = "Sign In",
                                            style = MaterialTheme.typography.titleSmall,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                }
                            }
                        }
                    }
                }

                // Error Message
                if (authState.error != null) {
                    Spacer(modifier = Modifier.height(16.dp))
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = Color(0xFFFEF2F2)
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text(
                            text = authState.error!!,
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFFDC2626),
                            modifier = Modifier.padding(16.dp)
                        )
                    }
                }

                // Loading Overlay
                if (authState.isLoading) {
                    Spacer(modifier = Modifier.height(16.dp))
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        shape = RoundedCornerShape(12.dp),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.Center
                        ) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = Primary,
                                strokeWidth = 2.dp
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Text(
                                text = "Signing in...",
                                style = MaterialTheme.typography.bodyMedium,
                                color = Color(0xFF64748B)
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))

                // Register Link
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Don't have an account? ",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color(0xFF64748B)
                    )
                    TextButton(onClick = onNavigateToRegister) {
                        Text(
                            text = "Sign Up",
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Bold,
                            color = Primary
                        )
                    }
                }

                Spacer(modifier = Modifier.height(32.dp))
            }
        }
    }
}

@Composable
fun AnimatedVisibility(
    visible: Boolean,
    content: @Composable ColumnScope.() -> Unit
) {
    if (visible) {
        Column(
            modifier = Modifier.fillMaxWidth(),
            content = content
        )
    }
}