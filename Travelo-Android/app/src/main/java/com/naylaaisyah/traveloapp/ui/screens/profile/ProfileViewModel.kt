package com.naylaaisyah.traveloapp.ui.screens.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Booking
import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import com.naylaaisyah.traveloapp.data.models.User
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import java.time.LocalDate
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class ProfileUiState(
    val userName: String? = null,
    val userEmail: String? = null,
    val userPhoto: String? = null,
    val phone: String? = null,
    val dateOfBirth: String? = null,
    val city: String? = null,
    val createdAt: String? = null,
    val isLoading: Boolean = false,
    val hasLoadedProfile: Boolean = false,
    val loadError: String? = null,
    val isEditOpen: Boolean = false,
    val editName: String = "",
    val editPhone: String = "",
    val editDateOfBirth: String = "",
    val editCity: String = "",
    val editNameError: String? = null,
    val editPhoneError: String? = null,
    val editCityError: String? = null,
    val isSaving: Boolean = false,
    val saveError: String? = null,
    val isUploadingPhoto: Boolean = false,
    val photoError: String? = null,
    val message: String? = null,
    val tripsCount: Int? = null,
    val upcomingTrips: Int? = null,
    val nextTrip: Booking? = null,
    val statsLoading: Boolean = false,
    val statsError: Boolean = false,
    val savedIdentity: SavedIdentity? = null,
    val savedIdentityLoading: Boolean = false,
    val hasLoadedSavedIdentity: Boolean = false,
    val savedIdentityError: String? = null,
    val isDeletingSavedIdentity: Boolean = false,
    val savedIdentityDeleteError: String? = null
)

/**
 * Profile state is backed by the API, not by editable UI-only fields.
 * The cached session values let the header render immediately while the
 * fresh profile and trip counts load in the background.
 */
@HiltViewModel
class ProfileViewModel @Inject constructor(
    private val repository: TraveloRepository,
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

    fun refresh() {
        loadProfile()
        loadTripStats()
        loadSavedIdentity()
    }

    fun loadProfile() {
        if (_uiState.value.isLoading) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.update {
                it.copy(
                    isLoading = false,
                    loadError = "Your session has expired. Please sign in again."
                )
            }
            return
        }

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isLoading = !it.hasLoadedProfile,
                    loadError = null
                )
            }

            when (val result = repository.getUserProfile(token)) {
                is Resource.Success -> applyProfile(result.data)
                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            loadError = result.message.ifBlank {
                                "Couldn't load your profile. Please try again."
                            }
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    fun loadTripStats() {
        if (_uiState.value.statsLoading) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) return

        viewModelScope.launch {
            _uiState.update { it.copy(statsLoading = true, statsError = false) }

            when (val result = repository.getMyBookings(token)) {
                is Resource.Success -> {
                    // Cancelled bookings are history, not trips worth counting.
                    val active = result.data.filterNot {
                        it.status.equals("cancelled", ignoreCase = true)
                    }
                    val today = LocalDate.now().toString()
                    val upcoming = active.count {
                        it.bookingDate.take(10) >= today
                    }
                    val nextTrip = nextUpcomingBooking(active, today)

                    _uiState.update {
                        it.copy(
                            tripsCount = active.size,
                            upcomingTrips = upcoming,
                            nextTrip = nextTrip,
                            statsLoading = false,
                            statsError = false
                        )
                    }
                }
                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            statsLoading = false,
                            statsError = true
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    fun loadSavedIdentity() {
        if (_uiState.value.savedIdentityLoading || _uiState.value.isDeletingSavedIdentity) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.update {
                it.copy(
                    savedIdentityLoading = false,
                    hasLoadedSavedIdentity = true,
                    savedIdentityError = "Your session has expired. Please sign in again."
                )
            }
            return
        }

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    savedIdentityLoading = true,
                    savedIdentityError = null
                )
            }

            when (val result = repository.getSavedIdentity(token)) {
                is Resource.Success -> {
                    _uiState.update {
                        it.copy(
                            savedIdentity = result.data,
                            savedIdentityLoading = false,
                            hasLoadedSavedIdentity = true,
                            savedIdentityError = null
                        )
                    }
                }
                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            savedIdentityLoading = false,
                            hasLoadedSavedIdentity = true,
                            savedIdentityError = result.message.ifBlank {
                                "Couldn't load your saved identity. Please try again."
                            }
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    fun deleteSavedIdentity() {
        if (_uiState.value.isDeletingSavedIdentity) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.update {
                it.copy(
                    savedIdentityDeleteError = "Your session has expired. Please sign in again."
                )
            }
            return
        }

        _uiState.update {
            it.copy(
                isDeletingSavedIdentity = true,
                savedIdentityDeleteError = null
            )
        }

        viewModelScope.launch {
            when (val result = repository.deleteSavedIdentity(token)) {
                is Resource.Success -> {
                    _uiState.update {
                        it.copy(
                            savedIdentity = null,
                            savedIdentityLoading = false,
                            hasLoadedSavedIdentity = true,
                            savedIdentityError = null,
                            isDeletingSavedIdentity = false,
                            savedIdentityDeleteError = null,
                            message = "Saved identity deleted."
                        )
                    }
                }
                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            isDeletingSavedIdentity = false,
                            savedIdentityDeleteError = result.message.ifBlank {
                                "Couldn't delete your saved identity. Please try again."
                            }
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    fun clearSavedIdentityDeleteError() {
        _uiState.update { it.copy(savedIdentityDeleteError = null) }
    }

    fun openEdit() {
        val state = _uiState.value
        if (!state.hasLoadedProfile || state.isSaving || state.isUploadingPhoto) return

        _uiState.update {
            it.copy(
                isEditOpen = true,
                editName = state.userName.orEmpty(),
                editPhone = state.phone.orEmpty(),
                editDateOfBirth = state.dateOfBirth.orEmpty(),
                editCity = state.city.orEmpty(),
                editNameError = null,
                editPhoneError = null,
                editCityError = null,
                saveError = null,
                photoError = null
            )
        }
    }

    fun dismissEdit() {
        if (_uiState.value.isSaving || _uiState.value.isUploadingPhoto) return
        _uiState.update { it.copy(isEditOpen = false, saveError = null, photoError = null) }
    }

    fun onEditNameChange(value: String) {
        _uiState.update { it.copy(editName = value, editNameError = null) }
    }

    fun onEditPhoneChange(value: String) {
        _uiState.update { it.copy(editPhone = value, editPhoneError = null) }
    }

    fun onEditDateOfBirthChange(value: String) {
        _uiState.update { it.copy(editDateOfBirth = value) }
    }

    fun onEditCityChange(value: String) {
        _uiState.update { it.copy(editCity = value, editCityError = null) }
    }

    fun saveProfile() {
        val state = _uiState.value
        if (state.isSaving) return

        val name = state.editName.trim()
        val phone = state.editPhone.trim()
        val city = state.editCity.trim()

        val nameError = when {
            name.isBlank() -> "Enter your name."
            name.length < 2 -> "Use at least 2 characters."
            name.length > 80 -> "Keep your name under 80 characters."
            else -> null
        }
        val phoneError = when {
            phone.isBlank() -> null
            !PHONE_PATTERN.matches(phone) -> "Enter a valid phone number."
            else -> null
        }
        val cityError = when {
            city.length > 100 -> "Keep your city under 100 characters."
            else -> null
        }

        if (nameError != null || phoneError != null || cityError != null) {
            _uiState.update {
                it.copy(
                    editNameError = nameError,
                    editPhoneError = phoneError,
                    editCityError = cityError,
                    saveError = null
                )
            }
            return
        }

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.update {
                it.copy(saveError = "Your session has expired. Please sign in again.")
            }
            return
        }

        _uiState.update { it.copy(isSaving = true, saveError = null) }

        viewModelScope.launch {
            when (
                val result = repository.updateProfile(
                    token = token,
                    name = name,
                    phone = phone.ifBlank { null },
                    dateOfBirth = state.editDateOfBirth.ifBlank { null },
                    city = city.ifBlank { null }
                )
            ) {
                is Resource.Success -> {
                    applyProfile(result.data)
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            isEditOpen = false,
                            message = "Profile updated."
                        )
                    }
                }
                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            saveError = result.message.ifBlank {
                                "Couldn't save your profile. Please try again."
                            }
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    fun uploadProfilePhoto(bytes: ByteArray, mimeType: String, fileName: String) {
        val state = _uiState.value
        if (state.isUploadingPhoto) return

        val token = sessionManager.authToken
        if (token.isNullOrBlank()) {
            _uiState.update {
                it.copy(photoError = "Your session has expired. Please sign in again.")
            }
            return
        }

        _uiState.update { it.copy(isUploadingPhoto = true, photoError = null) }

        viewModelScope.launch {
            when (val result = repository.uploadProfilePhoto(
                token = token,
                fileName = fileName,
                mimeType = mimeType,
                bytes = bytes
            )) {
                is Resource.Success -> {
                    applyProfile(result.data)
                    _uiState.update {
                        it.copy(
                            isUploadingPhoto = false,
                            photoError = null,
                            message = "Profile photo updated."
                        )
                    }
                }

                is Resource.Error -> {
                    _uiState.update {
                        it.copy(
                            isUploadingPhoto = false,
                            photoError = result.message.ifBlank {
                                "Couldn't update your profile photo. Please try again."
                            }
                        )
                    }
                }

                else -> Unit
            }
        }
    }

    fun showPhotoError(message: String) {
        _uiState.update { it.copy(photoError = message) }
    }

    fun clearPhotoError() {
        _uiState.update { it.copy(photoError = null) }
    }

    fun clearMessage() {
        _uiState.update { it.copy(message = null) }
    }

    fun clearLoadError() {
        _uiState.update { it.copy(loadError = null) }
    }

    private fun applyProfile(user: User) {
        sessionManager.saveUserName(user.name)
        sessionManager.saveUserEmail(user.email)
        sessionManager.saveUserPhoto(user.avatar)

        _uiState.update {
            it.copy(
                userName = user.name,
                userEmail = user.email,
                userPhoto = user.avatar,
                phone = user.phone?.takeIf(String::isNotBlank),
                dateOfBirth = user.dateOfBirth?.take(10),
                city = user.city?.takeIf(String::isNotBlank),
                createdAt = user.createdAt,
                isLoading = false,
                hasLoadedProfile = true,
                loadError = null
            )
        }
    }

    private companion object {
        val PHONE_PATTERN = Regex("^\\+?[0-9][0-9\\s().-]{5,29}$")
    }
}
