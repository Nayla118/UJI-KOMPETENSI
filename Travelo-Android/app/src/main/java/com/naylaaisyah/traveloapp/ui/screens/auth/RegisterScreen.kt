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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.rememberAsyncImagePainter
import com.naylaaisyah.traveloapp.ui.theme.Primary
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.common.api.ApiException
import com.google.android.gms.common.api.CommonStatusCodes

@Composable
fun RegisterScreen(
    onRegisterSuccess: () -> Unit,
    onNavigateToLogin: () -> Unit,
    viewModel: AuthViewModel = hiltViewModel()
) {
    val authState by viewModel.authState.collectAsState()

    var fullName by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var confirmPasswordVisible by remember { mutableStateOf(false) }
    var termsAccepted by remember { mutableStateOf(false) }

    // Google Sign-In Launcher
    val googleSignInLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        Log.d("GoogleSignIn", "RegisterScreen: GoogleSignIn result code: ${result.resultCode}")
        
        if (result.resultCode == Activity.RESULT_OK) {
            val data = result.data
            if (data != null) {
                val task = GoogleSignIn.getSignedInAccountFromIntent(data)
                try {
                    val googleAccount = task.getResult(ApiException::class.java)
                    val idToken = googleAccount.idToken
                    if (idToken != null) {
                        Log.d("GoogleSignIn", "RegisterScreen: Starting Firebase login with Google idToken")
                        viewModel.loginWithGoogle(idToken)
                    } else {
                        viewModel.handleGoogleSignInError("Failed to get ID token. Please try again.")
                    }
                } catch (e: ApiException) {
                    Log.e("GoogleSignIn", "RegisterScreen: ApiException - ${e.statusCode}", e)
                    val errorMessage = when (e.statusCode) {
                        CommonStatusCodes.CANCELED -> "Emulator or device doesn't have a Google account configured. Please add a Google account in Settings → Accounts → Add account → Google, or sign up with Email/Password below."
                        CommonStatusCodes.NETWORK_ERROR -> "Network error. Please check your connection."
                        CommonStatusCodes.SIGN_IN_REQUIRED -> "Please sign in with Google"
                        CommonStatusCodes.DEVELOPER_ERROR -> "Google sign in developer error. Check SHA fingerprint and OAuth client configuration."
                        CommonStatusCodes.INVALID_ACCOUNT -> "Selected Google account is invalid."
                        else -> "Google sign in failed (${e.statusCode}): ${e.localizedMessage} - Try Email/Password signup instead."
                    }
                    viewModel.handleGoogleSignInError(errorMessage)
                } catch (e: Exception) {
                    Log.e("GoogleSignIn", "RegisterScreen: Exception - ${e.message}", e)
                    viewModel.handleGoogleSignInError("An error occurred: ${e.message}. Try Email/Password signup instead.")
                }
            }
        } else {
            Log.w("GoogleSignIn", "RegisterScreen: Sign in cancelled with result code: ${result.resultCode}")
            viewModel.handleGoogleSignInError("Sign up was cancelled. Use Email/Password signup instead or add a Google account to your device.")
        }
    }

    LaunchedEffect(authState.isAuthenticated) {
        if (authState.isAuthenticated) {
            onRegisterSuccess()
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
            // Back Button
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.Start
            ) {
                Surface(
                    modifier = Modifier.size(40.dp),
                    shape = RoundedCornerShape(50),
                    color = Color.Transparent,
                    onClick = onNavigateToLogin
                ) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "←",
                            style = MaterialTheme.typography.titleLarge,
                            color = Color(0xFF1E293B)
                        )
                    }
                }
            }

            // Header Section
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(top = 8.dp, bottom = 16.dp),
                horizontalAlignment = Alignment.Start
            ) {
                Text(
                    text = "Create Account",
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF1E293B)
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "Join our travel community and start your journey today.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Start
                )
            }

            // Form Section
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(vertical = 16.dp),
                horizontalAlignment = Alignment.Start
            ) {
                // Full Name Field
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.Start
                ) {
                    Text(
                        text = "Full Name",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF1E293B),
                        modifier = Modifier.padding(start = 4.dp)
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedTextField(
                        value = fullName,
                        onValueChange = { fullName = it },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        placeholder = {
                            Text(
                                text = "Enter your full name",
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        leadingIcon = {
                            Text(
                                text = "👤",
                                style = MaterialTheme.typography.titleMedium,
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Text,
                            imeAction = ImeAction.Next
                        ),
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Primary,
                            unfocusedBorderColor = Color(0xFFE5E7EB),
                            focusedLeadingIconColor = Primary,
                            unfocusedLeadingIconColor = Color(0xFF9CA3AF),
                            focusedPlaceholderColor = Color(0xFF9CA3AF),
                            unfocusedPlaceholderColor = Color(0xFF9CA3AF)
                        )
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Email Field
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.Start
                ) {
                    Text(
                        text = "Email Address",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF1E293B),
                        modifier = Modifier.padding(start = 4.dp)
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        placeholder = {
                            Text(
                                text = "name@example.com",
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        leadingIcon = {
                            Text(
                                text = "✉",
                                style = MaterialTheme.typography.titleMedium,
                                color = Color(0xFF9CA3AF)
                            )
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
                            unfocusedLeadingIconColor = Color(0xFF9CA3AF),
                            focusedPlaceholderColor = Color(0xFF9CA3AF),
                            unfocusedPlaceholderColor = Color(0xFF9CA3AF)
                        )
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Password Field
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.Start
                ) {
                    Text(
                        text = "Password",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF1E293B),
                        modifier = Modifier.padding(start = 4.dp)
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        placeholder = {
                            Text(
                                text = "••••••••",
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        leadingIcon = {
                            Text(
                                text = "🔒",
                                style = MaterialTheme.typography.titleMedium,
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        trailingIcon = {
                            TextButton(
                                onClick = { passwordVisible = !passwordVisible }
                            ) {
                                Text(
                                    text = if (passwordVisible) "👁" else "👁",
                                    style = MaterialTheme.typography.titleMedium
                                )
                            }
                        },
                        visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Password,
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
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Confirm Password Field
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.Start
                ) {
                    Text(
                        text = "Confirm Password",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF1E293B),
                        modifier = Modifier.padding(start = 4.dp)
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedTextField(
                        value = confirmPassword,
                        onValueChange = { confirmPassword = it },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        placeholder = {
                            Text(
                                text = "••••••••",
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        leadingIcon = {
                            Text(
                                text = "🔑",
                                style = MaterialTheme.typography.titleMedium,
                                color = Color(0xFF9CA3AF)
                            )
                        },
                        visualTransformation = if (confirmPasswordVisible) VisualTransformation.None else PasswordVisualTransformation(),
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
                }

                Spacer(modifier = Modifier.height(24.dp))

                // Terms Checkbox
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.Top
                ) {
                    Checkbox(
                        checked = termsAccepted,
                        onCheckedChange = { termsAccepted = it },
                        colors = CheckboxDefaults.colors(
                            checkedColor = Primary,
                            uncheckedColor = Color(0xFFCBD5E1)
                        )
                    )
                    Spacer(modifier = Modifier.width(12.dp))
                    Column(
                        modifier = Modifier.padding(top = 4.dp)
                    ) {
                        Text(
                            text = "I agree to the ",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B)
                        )
                        InlineTextLink(
                            text = "Terms of Service",
                            onClick = { /* Open Terms */ },
                            style = MaterialTheme.typography.bodySmall,
                            fontWeight = FontWeight.SemiBold,
                            color = Primary
                        )
                        Text(
                            text = " and ",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B)
                        )
                        InlineTextLink(
                            text = "Privacy Policy",
                            onClick = { /* Open Privacy Policy */ },
                            style = MaterialTheme.typography.bodySmall,
                            fontWeight = FontWeight.SemiBold,
                            color = Primary
                        )
                        Text(
                            text = ".",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(32.dp))

                // Error Message
                if (authState.error != null) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = Color(0xFFEF4444).copy(alpha = 0.1f)
                        ),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text(
                            text = authState.error!!,
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFFEF4444),
                            modifier = Modifier.padding(12.dp)
                        )
                    }
                    Spacer(modifier = Modifier.height(16.dp))
                }

                // Sign Up Button
                Button(
                    onClick = {
                        if (termsAccepted) {
                            if (password == confirmPassword) {
                                viewModel.register(email, password)
                            } else {
                                viewModel.handleGoogleSignInError("Passwords do not match")
                            }
                        } else {
                            viewModel.handleGoogleSignInError("Please accept the terms and conditions")
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(64.dp),
                    enabled = fullName.isNotBlank() && 
                              email.isNotBlank() && 
                              password.isNotBlank() && 
                              confirmPassword.isNotBlank() && 
                              termsAccepted && 
                              !authState.isLoading,
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Primary,
                        disabledContainerColor = Primary.copy(alpha = 0.5f)
                    )
                ) {
                    if (authState.isLoading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(24.dp),
                            color = Color.White,
                            strokeWidth = 2.dp
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                    } else {
                        Text(
                            text = "Sign Up",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "→",
                            style = MaterialTheme.typography.titleMedium
                        )
                    }
                }
            }

            // Already have account
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(vertical = 24.dp),
                horizontalArrangement = Arrangement.Center
            ) {
                Text(
                    text = "Already have an account?",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color(0xFF64748B)
                )
                TextButton(
                    onClick = onNavigateToLogin
                ) {
                    Text(
                        text = "Sign In",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                }
            }

            // Or register with section
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(bottom = 40.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Divider with text
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Divider(
                        modifier = Modifier.weight(1f),
                        color = Color(0xFFE5E7EB)
                    )
                    Text(
                        text = "or register with",
                        style = MaterialTheme.typography.labelSmall,
                        color = Color(0xFF94A3B8),
                        modifier = Modifier.padding(horizontal = 16.dp)
                    )
                    Divider(
                        modifier = Modifier.weight(1f),
                        color = Color(0xFFE5E7EB)
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                // Google and Apple buttons
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    // Google Button
                    OutlinedButton(
                        onClick = {
                            val googleSignInClient = viewModel.getGoogleSignInClient()
                            googleSignInClient.signOut().addOnCompleteListener {
                                val signInIntent = googleSignInClient.signInIntent
                                googleSignInLauncher.launch(signInIntent)
                            }
                        },
                        modifier = Modifier
                            .weight(1f)
                            .height(48.dp),
                        enabled = !authState.isLoading,
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, Color(0xFFE5E7EB)),
                        colors = ButtonDefaults.outlinedButtonColors(
                            containerColor = Color.White,
                            contentColor = Color(0xFF1E293B)
                        )
                    ) {
                        Row(
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // Google G Icon
                            Text(
                                text = "G",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF4285F4)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "Google",
                                style = MaterialTheme.typography.labelLarge,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }

                    // Apple Button
                    OutlinedButton(
                        onClick = { /* Apple Sign In */ },
                        modifier = Modifier
                            .weight(1f)
                            .height(48.dp),
                        enabled = !authState.isLoading,
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, Color(0xFFE5E7EB)),
                        colors = ButtonDefaults.outlinedButtonColors(
                            containerColor = Color.White,
                            contentColor = Color(0xFF1E293B)
                        )
                    ) {
                        Row(
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // Apple Icon (using text approximation)
                            Text(
                                text = "🍎",
                                style = MaterialTheme.typography.titleMedium
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "Apple",
                                style = MaterialTheme.typography.labelLarge,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun InlineTextLink(
    text: String,
    onClick: () -> Unit,
    style: androidx.compose.ui.text.TextStyle,
    fontWeight: FontWeight,
    color: Color
) {
    Text(
        text = text,
        style = style,
        fontWeight = fontWeight,
        color = color,
        modifier = Modifier
    )
}

