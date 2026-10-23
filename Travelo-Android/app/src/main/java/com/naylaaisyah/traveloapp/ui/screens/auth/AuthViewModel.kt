package com.naylaaisyah.traveloapp.ui.screens.auth

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.common.api.ApiException
import com.naylaaisyah.traveloapp.data.models.photo
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.GoogleAuthProvider
import com.google.firebase.auth.FirebaseUser
import com.google.firebase.auth.FirebaseAuthException
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

data class AuthState(
    val isLoading: Boolean = false,
    val isAuthenticated: Boolean = false,
    val user: FirebaseUser? = null,
    val error: String? = null,
    // Email OTP verification (step 2 of login).
    // The session token is only issued after `verifyOtp()` succeeds.
    val otpPending: Boolean = false,
    val otpEmail: String? = null,
    val otpSending: Boolean = false,
    val otpVerifying: Boolean = false,
    val otpResendAfter: Int = 60
)

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val firebaseAuth: FirebaseAuth,
    private val googleSignInClient: GoogleSignInClient,
    private val sessionManager: SessionManager,
    private val repository: TraveloRepository
) : ViewModel() {
    
    private val _authState = MutableStateFlow(AuthState())
    val authState: StateFlow<AuthState> = _authState.asStateFlow()
    
    init {
        checkCurrentUser()
    }
    
    private fun checkCurrentUser() {
        val currentUser = firebaseAuth.currentUser
        when {
            // A Firebase session only counts together with a backend token —
            // that token is issued after the email OTP has been verified.
            currentUser != null && sessionManager.isLoggedIn() -> {
                _authState.value = AuthState(
                    isAuthenticated = true,
                    user = currentUser
                )
            }
            currentUser != null -> {
                // Signed in with Firebase but the OTP was never finished (or the
                // backend session expired): clean up and start from the form.
                try {
                    firebaseAuth.signOut()
                } catch (e: Exception) {
                    Log.e("AuthViewModel", "Firebase signOut failed: ${e.message}")
                }
                Log.d("AuthViewModel", "No backend session — cleared half-finished login")
            }
        }
    }
    
    fun getGoogleSignInClient(): GoogleSignInClient = googleSignInClient
    
    fun loginWithEmail(email: String, password: String) {
        viewModelScope.launch {
            Log.d("AuthViewModel", "Starting email login")
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val result = firebaseAuth.signInWithEmailAndPassword(email, password).await()
                Log.d("AuthViewModel", "Firebase email login successful")
                
                result.user?.let { user ->
                    // Login only: the account was already email-verified when it
                    // was registered, so go straight in (no OTP here).
                    loginAndFinish(user, source = "email login")
                } ?: run {
                    Log.e("AuthViewModel", "Email login user is null")
                    _authState.value = AuthState(
                        isLoading = false,
                        error = "Login failed. User is null."
                    )
                }
            } catch (e: com.google.firebase.auth.FirebaseAuthInvalidUserException) {
                Log.e("AuthViewModel", "Invalid user: ${e.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Email not found. Please sign up first."
                )
            } catch (e: com.google.firebase.auth.FirebaseAuthInvalidCredentialsException) {
                Log.e("AuthViewModel", "Invalid credentials: ${e.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Invalid email or password. Please try again."
                )
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Email login exception: ${e.message}", e)
                val errorMessage = when {
                    e.message?.contains("There is no user record corresponding to this identifier") == true -> {
                        "Email not found. Please sign up first."
                    }
                    e.message?.contains("The password is invalid") == true -> {
                        "Invalid password. Please try again."
                    }
                    e.message?.contains("too many unsuccessful login attempts") == true -> {
                        "Too many failed login attempts. Please try again later."
                    }
                    else -> "Login failed: ${e.message ?: "Unknown error"}"
                }
                _authState.value = AuthState(
                    isLoading = false,
                    error = errorMessage
                )
            }
        }
    }
    
    fun loginWithGoogle(idToken: String) {
        viewModelScope.launch {
            Log.d("AuthViewModel", "Starting Google login with idToken")
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val credential = GoogleAuthProvider.getCredential(idToken, null)
                Log.d("AuthViewModel", "Created Google credential from idToken")
                
                val result = firebaseAuth.signInWithCredential(credential).await()
                Log.d("AuthViewModel", "Firebase sign-in successful")
                
                result.user?.let { firebaseUser ->
                    // Login only: no OTP at sign-in (verified earlier at sign-up).
                    loginAndFinish(firebaseUser, source = "google login")
                }
            } catch (e: ApiException) {
                Log.e("AuthViewModel", "ApiException in Google login: ${e.statusCode} - ${e.message}", e)
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Google login failed: ${e.message}"
                )
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Exception in Google login: ${e.message}", e)
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Google login failed: ${e.message}"
                )
            }
        }
    }
    
    fun signInWithGoogleFromActivity() {
        viewModelScope.launch {
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val signInIntent = googleSignInClient.signInIntent
                // The activity will handle launching the intent
                // Results are returned via onActivityResult
            } catch (e: Exception) {
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Failed to start Google sign-in: ${e.message}"
                )
            }
        }
    }
    
    fun handleGoogleSignInResult(task: com.google.android.gms.tasks.Task<com.google.android.gms.auth.api.signin.GoogleSignInResult>) {
        viewModelScope.launch {
            try {
                val result = task.await()
                if (result.isSuccess) {
                    val googleUser = result.signInAccount
                    val idToken = googleUser?.idToken
                    if (idToken != null) {
                        loginWithGoogle(idToken)
                    } else {
                        _authState.value = AuthState(
                            isLoading = false,
                            error = "Failed to get Google ID token"
                        )
                    }
                } else {
                    _authState.value = AuthState(
                        isLoading = false,
                        error = "Google sign-in failed: ${result.status}"
                    )
                }
            } catch (e: ApiException) {
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Google sign-in error: ${e.message}"
                )
            } catch (e: Exception) {
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Google sign-in failed: ${e.message}"
                )
            }
        }
    }
    
    fun register(email: String, password: String) {
        viewModelScope.launch {
            Log.d("AuthViewModel", "Starting registration")
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val result = firebaseAuth.createUserWithEmailAndPassword(email, password).await()
                Log.d("AuthViewModel", "Firebase registration successful")
                
                result.user?.let { firebaseUser ->
                    // Registration always goes through email verification: the
                    // account only becomes usable after the code is confirmed.
                    beginEmailOtp(firebaseUser, source = "register")
                }
            } catch (e: com.google.firebase.auth.FirebaseAuthUserCollisionException) {
                Log.e("AuthViewModel", "Email already exists: ${e.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Email already registered. Please sign in or use a different email."
                )
            } catch (e: com.google.firebase.auth.FirebaseAuthWeakPasswordException) {
                Log.e("AuthViewModel", "Weak password: ${e.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Password too weak. Use at least 6 characters."
                )
            } catch (e: com.google.firebase.auth.FirebaseAuthInvalidCredentialsException) {
                Log.e("AuthViewModel", "Invalid email: ${e.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Invalid email address. Please check and try again."
                )
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Registration exception: ${e.message}", e)
                val errorMessage = when {
                    e.message?.contains("email address is already in use") == true -> {
                        "Email already registered. Please sign in or use a different email."
                    }
                    e.message?.contains("password is too weak") == true -> {
                        "Password too weak. Use at least 6 characters with letters and numbers."
                    }
                    e.message?.contains("invalid email") == true -> {
                        "Invalid email address. Please enter a valid email."
                    }
                    else -> "Registration failed: ${e.message ?: "Unknown error"}"
                }
                _authState.value = AuthState(
                    isLoading = false,
                    error = errorMessage
                )
            }
        }
    }
    
    /**
     * Sign-in path (email/password + Google).
     *
     * The OTP is only required at registration, so the account reaching this
     * point has already been verified. We just sync the Firebase identity with
     * the backend and open the session.
     */
    private suspend fun loginAndFinish(firebaseUser: FirebaseUser, source: String) {
        val idToken = firebaseUser.getIdToken(false).await().token
        if (idToken == null) {
            Log.e("AuthViewModel", "[$source] Could not get Firebase idToken")
            _authState.value = AuthState(
                isLoading = false,
                error = "Couldn't set up your session. Please try again."
            )
            return
        }

        Log.d("AuthViewModel", "[$source] Syncing with backend (no OTP needed at login)")
        when (val resource = repository.login(idToken)) {
            is Resource.Success -> {
                val backendUser = resource.data.user
                val token = resource.data.token

                if (backendUser == null || token == null) {
                    Log.e("AuthViewModel", "[$source] Backend returned no user/token")
                    _authState.value = AuthState(
                        isLoading = false,
                        error = "Sign-in failed: the server response was incomplete."
                    )
                    return
                }

                storeSession(backendUser, token)
                Log.d("AuthViewModel", "[$source] Backend session issued")
                _authState.value = AuthState(
                    isLoading = false,
                    isAuthenticated = true,
                    user = firebaseUser
                )
            }
            is Resource.Error -> {
                Log.e("AuthViewModel", "[$source] Backend login failed: ${resource.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = resource.message.ifBlank {
                        "Can't reach the server. Check your connection and try again."
                    }
                )
            }
            else -> Unit
        }
    }

    /**
     * Asks the backend to email a 6-digit code and switches this state machine
     * to the verification step. Called after a successful registration.
     */
    private suspend fun beginEmailOtp(firebaseUser: FirebaseUser, source: String) {
        val email = firebaseUser.email
        if (email.isNullOrBlank()) {
            Log.e("AuthViewModel", "[$source] Firebase user has no email — cannot verify")
            _authState.value = AuthState(
                isLoading = false,
                error = "This account has no email address, so it can't be verified."
            )
            return
        }

        val idToken = firebaseUser.getIdToken(false).await().token
        if (idToken == null) {
            Log.e("AuthViewModel", "[$source] Could not get Firebase idToken for OTP")
            _authState.value = AuthState(
                isLoading = false,
                error = "Couldn't start verification. Please try again."
            )
            return
        }

        Log.d("AuthViewModel", "[$source] Requesting login OTP")
        when (val resource = repository.sendOtp(idToken)) {
            is Resource.Success -> {
                _authState.value = AuthState(
                    isLoading = false,
                    otpPending = true,
                    otpEmail = resource.data.email ?: email,
                    otpResendAfter = resource.data.resendAfter
                )
            }
            is Resource.Error -> {
                Log.e("AuthViewModel", "[$source] OTP send failed: ${resource.message}")
                _authState.value = AuthState(
                    isLoading = false,
                    error = resource.message.ifBlank {
                        "Couldn't send the verification code to your email. Please try again."
                    }
                )
            }
            else -> Unit
        }
    }

    /**
     * Verifies the 6-digit code from the OTP screen. On success the backend
     * returns the session token, which is stored before marking the user
     * authenticated — so an unverified login can never complete.
     */
    fun verifyOtp(code: String) {
        if (code.length != 6) return

        viewModelScope.launch {
            _authState.value = _authState.value.copy(otpVerifying = true, error = null)

            val user = firebaseAuth.currentUser
            if (user == null) {
                _authState.value = AuthState(
                    isLoading = false,
                    error = "Your session has expired. Please sign in again."
                )
                return@launch
            }

            try {
                val idToken = user.getIdToken(false).await().token
                if (idToken == null) {
                    _authState.value = _authState.value.copy(
                        otpVerifying = false,
                        error = "Couldn't verify the code. Please try again."
                    )
                    return@launch
                }

                when (val resource = repository.verifyOtp(idToken, code)) {
                    is Resource.Success -> {
                        storeSession(resource.data.first, resource.data.second)
                        Log.d("AuthViewModel", "OTP verified — session token issued")
                        _authState.value = AuthState(
                            isLoading = false,
                            isAuthenticated = true,
                            user = user
                        )
                    }
                    is Resource.Error -> {
                        _authState.value = _authState.value.copy(
                            otpVerifying = false,
                            error = resource.message.ifBlank {
                                "That code is incorrect or has expired."
                            }
                        )
                    }
                    else -> Unit
                }
            } catch (e: Exception) {
                Log.e("AuthViewModel", "verifyOtp exception: ${e.message}", e)
                _authState.value = _authState.value.copy(
                    otpVerifying = false,
                    error = "Couldn't reach the server. Check your connection."
                )
            }
        }
    }

    /** Requests a fresh code; the backend enforces the 60-second cooldown. */
    fun resendOtp() {
        viewModelScope.launch {
            _authState.value = _authState.value.copy(otpSending = true, error = null)

            val idToken = firebaseAuth.currentUser?.getIdToken(false)?.await()?.token
            if (idToken == null) {
                _authState.value = _authState.value.copy(
                    otpSending = false,
                    error = "Your session has expired. Please sign in again."
                )
                return@launch
            }

            when (val resource = repository.resendOtp(idToken)) {
                is Resource.Success -> {
                    _authState.value = _authState.value.copy(
                        otpSending = false,
                        otpResendAfter = resource.data.resendAfter,
                        error = null
                    )
                }
                is Resource.Error -> {
                    _authState.value = _authState.value.copy(
                        otpSending = false,
                        error = resource.message.ifBlank {
                            "Couldn't resend the code. Please try again."
                        }
                    )
                }
                else -> Unit
            }
        }
    }

    /** The user backed out of the OTP screen: abort the half-finished login. */
    fun cancelOtp() {
        try {
            firebaseAuth.signOut()
        } catch (e: Exception) {
            Log.e("AuthViewModel", "Firebase signOut on cancel failed: ${e.message}")
        }
        _authState.value = AuthState()
        Log.d("AuthViewModel", "OTP step cancelled by user")
    }

    private fun storeSession(user: com.naylaaisyah.traveloapp.data.models.User, token: String) {
        sessionManager.authToken = token
        sessionManager.userEmail = user.email
        sessionManager.userId = user.id.toString()
        sessionManager.userName = user.name
        sessionManager.userPhoto = user.photo
    }

    fun logout() {
        // Clear the local session and Firebase user before the UI navigates to
        // Login. LoginScreen creates a new AuthViewModel whose init() would
        // otherwise see the old Firebase user + still-present token and send
        // the user straight back to Home.
        sessionManager.clearSession()
        _authState.value = AuthState()

        try {
            firebaseAuth.signOut()
            Log.d("AuthViewModel", "Firebase sign out successful")
        } catch (e: Exception) {
            Log.e("AuthViewModel", "Error during Firebase sign out: ${e.message}", e)
        }

        // Provider cleanup is best-effort and may finish after the activity
        // leaves; clearing the local session above is the authoritative part.
        viewModelScope.launch {
            try {
                // Both signOut() and revokeAccess() to completely clear Google Sign-In cache
                // signOut() only signs out, but revokeAccess() removes the cached account
                // so the account picker will show on next sign-in
                Log.d("AuthViewModel", "Signing out from Google...")
                googleSignInClient.signOut().await()
                Log.d("AuthViewModel", "Revoking Google access...")
                googleSignInClient.revokeAccess().await()
                Log.d("AuthViewModel", "Google sign out successful")
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Error during Google sign out: ${e.message}", e)
            }
        }
    }

    fun clearError() {
        _authState.value = _authState.value.copy(error = null)
    }
    
    fun setError(message: String) {
        _authState.value = _authState.value.copy(isLoading = false, error = message)
    }

    fun handleGoogleSignInError(message: String) {
        setError(message)
    }
}

