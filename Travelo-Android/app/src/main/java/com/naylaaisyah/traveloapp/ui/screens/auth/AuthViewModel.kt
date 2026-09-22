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
    val error: String? = null
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
        if (currentUser != null) {
            _authState.value = AuthState(
                isAuthenticated = true,
                user = currentUser
            )
        }
    }
    
    fun getGoogleSignInClient(): GoogleSignInClient = googleSignInClient
    
    fun loginWithEmail(email: String, password: String) {
        viewModelScope.launch {
            Log.d("AuthViewModel", "Starting email login for: $email")
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val result = firebaseAuth.signInWithEmailAndPassword(email, password).await()
                Log.d("AuthViewModel", "Firebase email login successful")
                
                result.user?.let { user ->
                    // Get Firebase ID token to sync with backend
                    val idToken = user.getIdToken(false).await().token
                    if (idToken != null) {
                        // Sync with backend
                        Log.d("AuthViewModel", "Syncing email login with backend...")
                        repository.firebaseLogin(idToken).collect { resource ->
                            when (resource) {
                                is Resource.Loading<*> -> {
                                    Log.d("AuthViewModel", "Backend sync loading...")
                                }
                                is Resource.Success<*> -> {
                                    Log.d("AuthViewModel", "Backend sync successful")
                                    (resource.data as? Pair<*, *>)?.let { pair ->
                                        val backendUser = pair.first as? com.naylaaisyah.traveloapp.data.models.User
                                        val token = pair.second as? String
                                        if (backendUser != null && token != null) {
                                            sessionManager.authToken = token
                                            sessionManager.userEmail = backendUser.email
                                            sessionManager.userId = backendUser.id.toString()
                                            sessionManager.userName = backendUser.name
                                            sessionManager.userPhoto = backendUser.photo
                                        }
                                    }
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = user
                                    )
                                }
                                is Resource.Error<*> -> {
                                    Log.w("AuthViewModel", "Backend sync failed, allowing local login: ${resource.message}")
                                    // Still allow login but show warning
                                    sessionManager.userEmail = user.email
                                    sessionManager.userId = user.uid
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = user
                                    )
                                }
                            }
                        }
                    } else {
                        // Fallback if can't get ID token
                        Log.w("AuthViewModel", "No ID token, allowing local login")
                        sessionManager.userEmail = user.email
                        sessionManager.userId = user.uid
                        _authState.value = AuthState(
                            isLoading = false,
                            isAuthenticated = true,
                            user = user
                        )
                    }
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
                Log.d("AuthViewModel", "Firebase sign-in successful: ${result.user?.email}")
                
                result.user?.let { firebaseUser ->
                    // Get fresh ID token for backend verification
                    val backendIdToken = firebaseUser.getIdToken(false).await().token
                    Log.d("AuthViewModel", "Got backend idToken: ${backendIdToken != null}")
                    
                    if (backendIdToken != null) {
                        // Sync with backend
                        Log.d("AuthViewModel", "Syncing with backend...")
                        repository.firebaseLogin(backendIdToken).collect { resource ->
                            when (resource) {
                                is Resource.Loading<*> -> {
                                    Log.d("AuthViewModel", "Backend login loading...")
                                    // Already showing loading
                                }
                                is Resource.Success<*> -> {
                                    Log.d("AuthViewModel", "Backend login successful")
                                    (resource.data as? Pair<*, *>)?.let { pair ->
                                        val backendUser = pair.first as? com.naylaaisyah.traveloapp.data.models.User
                                        val token = pair.second as? String
                                        if (backendUser != null && token != null) {
                                            sessionManager.authToken = token
                                            sessionManager.userEmail = backendUser.email
                                            sessionManager.userId = backendUser.id.toString()
                                            sessionManager.userName = backendUser.name
                                            sessionManager.userPhoto = backendUser.photo
                                        }
                                    }
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = firebaseUser
                                    )
                                }
                                is Resource.Error<*> -> {
                                    Log.w("AuthViewModel", "Backend login failed: ${resource.message}, allowing local login")
                                    // Still allow login but store Firebase UID
                                    sessionManager.userEmail = firebaseUser.email
                                    sessionManager.userId = firebaseUser.uid
                                    sessionManager.userName = firebaseUser.displayName
                                    sessionManager.userPhoto = firebaseUser.photoUrl?.toString()
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = firebaseUser
                                    )
                                }
                            }
                        }
                    } else {
                        Log.w("AuthViewModel", "No backend idToken, using Firebase info")
                        // Fallback - store Firebase info locally
                        sessionManager.userEmail = firebaseUser.email
                        sessionManager.userId = firebaseUser.uid
                        sessionManager.userName = firebaseUser.displayName
                        sessionManager.userPhoto = firebaseUser.photoUrl?.toString()
                        _authState.value = AuthState(
                            isLoading = false,
                            isAuthenticated = true,
                            user = firebaseUser
                        )
                    }
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
            Log.d("AuthViewModel", "Starting registration for: $email")
            _authState.value = _authState.value.copy(isLoading = true, error = null)
            try {
                val result = firebaseAuth.createUserWithEmailAndPassword(email, password).await()
                Log.d("AuthViewModel", "Firebase registration successful")
                
                result.user?.let { firebaseUser ->
                    // Get Firebase ID token to sync with backend
                    val idToken = firebaseUser.getIdToken(false).await().token
                    if (idToken != null) {
                        // Sync with backend
                        Log.d("AuthViewModel", "Syncing registration with backend...")
                        repository.firebaseLogin(idToken).collect { resource ->
                            when (resource) {
                                is Resource.Loading<*> -> {
                                    Log.d("AuthViewModel", "Backend registration sync loading...")
                                }
                                is Resource.Success<*> -> {
                                    Log.d("AuthViewModel", "Backend registration sync successful")
                                    (resource.data as? Pair<*, *>)?.let { pair ->
                                        val backendUser = pair.first as? com.naylaaisyah.traveloapp.data.models.User
                                        val token = pair.second as? String
                                        if (backendUser != null && token != null) {
                                            sessionManager.authToken = token
                                            sessionManager.userEmail = backendUser.email
                                            sessionManager.userId = backendUser.id.toString()
                                            sessionManager.userName = backendUser.name
                                            sessionManager.userPhoto = backendUser.photo
                                        }
                                    }
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = firebaseUser
                                    )
                                }
                                is Resource.Error<*> -> {
                                    Log.w("AuthViewModel", "Backend registration sync failed, allowing local account: ${resource.message}")
                                    // Still allow registration but store Firebase UID
                                    sessionManager.userEmail = firebaseUser.email
                                    sessionManager.userId = firebaseUser.uid
                                    sessionManager.userName = firebaseUser.displayName
                                    sessionManager.userPhoto = firebaseUser.photoUrl?.toString()
                                    _authState.value = AuthState(
                                        isLoading = false,
                                        isAuthenticated = true,
                                        user = firebaseUser
                                    )
                                }
                            }
                        }
                    } else {
                        // Fallback - store Firebase info locally
                        Log.w("AuthViewModel", "No ID token, allowing local account")
                        sessionManager.userEmail = firebaseUser.email
                        sessionManager.userId = firebaseUser.uid
                        sessionManager.userName = firebaseUser.displayName
                        sessionManager.userPhoto = firebaseUser.photoUrl?.toString()
                        _authState.value = AuthState(
                            isLoading = false,
                            isAuthenticated = true,
                            user = firebaseUser
                        )
                    }
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
    
    fun logout() {
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
            try {
                firebaseAuth.signOut()
                Log.d("AuthViewModel", "Firebase sign out successful")
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Error during Firebase sign out: ${e.message}", e)
            }
            sessionManager.clearSession()
            _authState.value = AuthState()
            Log.d("AuthViewModel", "Logout complete")
        }
    }
    
    fun clearError() {
        _authState.value = _authState.value.copy(error = null)
    }
    
    fun handleGoogleSignInError(message: String) {
        _authState.value = AuthState(
            isLoading = false,
            error = message
        )
    }
}

