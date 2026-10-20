package com.naylaaisyah.traveloapp.ui.screens.auth

import android.app.Activity
import android.util.Log
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.VpnKey
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.semantics.Role
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
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing

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

    val emailFocus = remember { FocusRequester() }
    val passwordFocus = remember { FocusRequester() }
    val confirmPasswordFocus = remember { FocusRequester() }
    val keyboard = LocalSoftwareKeyboardController.current
    val error = authState.error

    // Inline validation mirrors the backend rules (min 8 characters, confirmed).
    val passwordTooShort = password.isNotEmpty() && password.length < 8
    val passwordsMismatch = confirmPassword.isNotEmpty() && password != confirmPassword

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
                        CommonStatusCodes.CANCELED -> "No Google account found on this device. Add one in Settings, or sign up with email below."
                        CommonStatusCodes.NETWORK_ERROR -> "Network error. Please check your connection."
                        CommonStatusCodes.SIGN_IN_REQUIRED -> "Please sign in with Google"
                        CommonStatusCodes.DEVELOPER_ERROR -> "Google sign in is not configured correctly. Try email sign up instead."
                        CommonStatusCodes.INVALID_ACCOUNT -> "Selected Google account is invalid."
                        else -> "Google sign in failed (${e.statusCode}). Try email sign up instead."
                    }
                    viewModel.handleGoogleSignInError(errorMessage)
                } catch (e: Exception) {
                    Log.e("GoogleSignIn", "RegisterScreen: Exception - ${e.message}", e)
                    viewModel.handleGoogleSignInError("An error occurred. Try email sign up instead.")
                }
            }
        } else {
            Log.w("GoogleSignIn", "RegisterScreen: Sign up cancelled with result code: ${result.resultCode}")
        }
    }

    LaunchedEffect(authState.isAuthenticated) {
        if (authState.isAuthenticated) {
            onRegisterSuccess()
        }
    }

    fun submit() {
        keyboard?.hide()
        when {
            !termsAccepted -> viewModel.setError("Please accept the terms and conditions")
            password.length < 8 -> viewModel.setError("Password must be at least 8 characters")
            password != confirmPassword -> viewModel.setError("Passwords do not match")
            else -> viewModel.register(email, password)
        }
    }

    fun googleSignIn() {
        val googleSignInClient = viewModel.getGoogleSignInClient()
        googleSignInClient.signOut().addOnCompleteListener {
            googleSignInLauncher.launch(googleSignInClient.signInIntent)
        }
    }

    // Step 2 of registration: verify the emailed code before entering the app.
    if (authState.otpPending) {
        OtpVerification(
            maskedEmail = authState.otpEmail.orEmpty(),
            resendAfter = authState.otpResendAfter,
            isVerifying = authState.otpVerifying,
            isResending = authState.otpSending,
            error = error,
            onVerify = { viewModel.verifyOtp(it) },
            onResend = { viewModel.resendOtp() },
            onBack = { viewModel.cancelOtp() },
            onDismissError = { viewModel.clearError() }
        )
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .verticalScroll(rememberScrollState())
            .imePadding()
    ) {
        AuthBrandBar(onBackClick = onNavigateToLogin)

        Spacer(modifier = Modifier.height(8.dp))

        AuthHeader(
            eyebrow = "Travelo account",
            title = "Create your account",
            subtitle = "One account for every trip you plan, book and remember."
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
                value = fullName,
                onValueChange = { fullName = it },
                label = "Full name",
                placeholder = "As written on your ID",
                leadingIcon = Icons.Outlined.Person,
                keyboardType = KeyboardType.Text,
                imeAction = ImeAction.Next,
                onImeAction = {
                    keyboard?.show()
                    emailFocus.requestFocus()
                },
                enabled = !authState.isLoading
            )

            Spacer(modifier = Modifier.height(16.dp))

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
                enabled = !authState.isLoading,
                modifier = Modifier.focusRequester(emailFocus)
            )

            Spacer(modifier = Modifier.height(16.dp))

            AuthTextField(
                value = password,
                onValueChange = { password = it },
                label = "Password",
                placeholder = "At least 8 characters",
                leadingIcon = Icons.Outlined.Lock,
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Next,
                onImeAction = {
                    keyboard?.show()
                    confirmPasswordFocus.requestFocus()
                },
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
                isError = passwordTooShort,
                supportingText = if (passwordTooShort) "Use at least 8 characters" else null,
                modifier = Modifier.focusRequester(passwordFocus)
            )

            Spacer(modifier = Modifier.height(16.dp))

            AuthTextField(
                value = confirmPassword,
                onValueChange = { confirmPassword = it },
                label = "Confirm password",
                placeholder = "Re-enter your password",
                leadingIcon = Icons.Outlined.VpnKey,
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Done,
                onImeAction = { submit() },
                enabled = !authState.isLoading,
                visualTransformation = if (confirmPasswordVisible) {
                    VisualTransformation.None
                } else {
                    PasswordVisualTransformation()
                },
                trailingIcon = {
                    IconButton(onClick = { confirmPasswordVisible = !confirmPasswordVisible }) {
                        Icon(
                            imageVector = if (confirmPasswordVisible) {
                                Icons.Filled.VisibilityOff
                            } else {
                                Icons.Filled.Visibility
                            },
                            contentDescription = if (confirmPasswordVisible) "Hide password" else "Show password",
                            tint = Gray400
                        )
                    }
                },
                isError = passwordsMismatch,
                supportingText = if (passwordsMismatch) "Passwords do not match" else null,
                modifier = Modifier.focusRequester(confirmPasswordFocus)
            )

            Spacer(modifier = Modifier.height(16.dp))

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 48.dp)
                    .toggleable(
                        value = termsAccepted,
                        enabled = !authState.isLoading,
                        role = Role.Checkbox,
                        onValueChange = { termsAccepted = it }
                    ),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Checkbox(
                    checked = termsAccepted,
                    onCheckedChange = null,
                    colors = CheckboxDefaults.colors(
                        checkedColor = Rosewood,
                        uncheckedColor = Gray400
                    )
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "I agree to Travelo's terms of service and privacy policy.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted,
                    modifier = Modifier.weight(1f)
                )
            }

            Spacer(modifier = Modifier.height(20.dp))

            AuthPrimaryButton(
                text = "Create account",
                onClick = { submit() },
                enabled = fullName.isNotBlank() &&
                    email.isNotBlank() &&
                    password.isNotBlank() &&
                    confirmPassword.isNotBlank() &&
                    termsAccepted &&
                    !passwordTooShort &&
                    !passwordsMismatch,
                isLoading = authState.isLoading
            )

            Spacer(modifier = Modifier.height(22.dp))

            AuthDivider("or continue with")

            Spacer(modifier = Modifier.height(20.dp))

            GoogleAuthButton(onClick = { googleSignIn() }, enabled = !authState.isLoading)

            Spacer(modifier = Modifier.height(20.dp))

            AuthFooterLink(
                prompt = "Already have an account?",
                actionLabel = "Sign in",
                onClick = onNavigateToLogin
            )

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}
