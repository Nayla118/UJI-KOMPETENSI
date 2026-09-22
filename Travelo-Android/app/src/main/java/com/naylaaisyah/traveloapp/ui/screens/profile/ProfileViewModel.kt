package com.naylaaisyah.traveloapp.ui.screens.profile

import androidx.lifecycle.ViewModel
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import com.naylaaisyah.traveloapp.util.SessionManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

data class ProfileUiState(
    val userName: String? = null,
    val userEmail: String? = null,
    val userPhoto: String? = null
)

@HiltViewModel
class ProfileViewModel @Inject constructor(
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(
        ProfileUiState(
            userName = sessionManager.userName,
            userEmail = sessionManager.userEmail,
            userPhoto = sessionManager.userPhoto
        )
    )
    val uiState: StateFlow<ProfileUiState> = _uiState.asStateFlow()

    fun logout() {
        sessionManager.clearSession()
        _uiState.value = ProfileUiState()
    }
}