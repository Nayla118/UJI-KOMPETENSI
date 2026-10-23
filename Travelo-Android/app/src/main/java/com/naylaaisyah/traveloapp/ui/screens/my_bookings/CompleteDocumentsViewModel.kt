package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Visitor
import com.naylaaisyah.traveloapp.data.models.VisitorDocumentInput
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryRules
import com.naylaaisyah.traveloapp.data.recovery.DocumentRecoveryStore
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CompleteDocumentsUiState(
    val isLoading: Boolean = false,
    val visitors: List<Visitor> = emptyList(),
    val uploading: Boolean = false,
    val completed: Boolean = false,
    val error: String? = null
)

/**
 * Resumes document uploads for an existing booking.
 *
 * The booking id comes from navigation, never from a freshly created booking.
 * The backend visitor list is the authority for which files are still needed,
 * so a process death, a lost response or a partial upload can never cause a
 * duplicate booking or overwrite an accepted document.
 */
@HiltViewModel
class CompleteDocumentsViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager,
    private val recoveryStore: DocumentRecoveryStore,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    private val bookingId: Int = savedStateHandle.get<Int>("bookingId") ?: 0

    private val _uiState = MutableStateFlow(CompleteDocumentsUiState())
    val uiState: StateFlow<CompleteDocumentsUiState> = _uiState.asStateFlow()

    init {
        load()
    }

    fun load() {
        val token = sessionManager.authToken
        val userId = sessionManager.userId

        if (bookingId <= 0) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "This booking could not be opened."
            )
            return
        }

        if (token.isNullOrBlank() || userId.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "Your session has expired. Please sign in again."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            when (val result = repository.getVisitors(token, bookingId)) {
                is Resource.Success -> {
                    applyAuthoritativeVisitors(result.data, userId)
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message.ifBlank {
                            "Couldn't load this booking. Check your connection and try again."
                        }
                    )
                }
                else -> Unit
            }
        }
    }

    /**
     * Uploads only visitors that still have no document on file. Selections are
     * read into memory by the screen at this moment; a stale picker URI becomes
     * a normal "choose it again" error instead of a crash.
     */
    fun uploadMissing(documents: Map<Int, VisitorDocumentInput>) {
        if (_uiState.value.uploading) return

        val token = sessionManager.authToken
        val userId = sessionManager.userId

        if (token.isNullOrBlank() || userId.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                error = "Your session has expired. Please sign in again."
            )
            return
        }

        if (bookingId <= 0) {
            _uiState.value = _uiState.value.copy(error = "This booking could not be opened.")
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                uploading = true,
                error = null,
                completed = false
            )
            recoveryStore.markAttempt(bookingId, userId)

            // Re-read before uploading. If an earlier response was lost after
            // the server stored the file, this refresh prevents a duplicate.
            val freshVisitors = when (val refreshed = repository.getVisitors(token, bookingId)) {
                is Resource.Success -> refreshed.data
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        uploading = false,
                        error = refreshed.message.ifBlank {
                            "Can't check the current document status. Check your connection and try again."
                        }
                    )
                    return@launch
                }
                else -> {
                    _uiState.value = _uiState.value.copy(uploading = false)
                    return@launch
                }
            }
            val missing = DocumentRecoveryRules.visitorsRequiringUpload(freshVisitors)

            if (missing.isEmpty()) {
                recoveryStore.clear(bookingId, userId)
                _uiState.value = _uiState.value.copy(
                    visitors = freshVisitors,
                    uploading = false,
                    completed = true,
                    error = null
                )
                return@launch
            }

            if (DocumentRecoveryRules.visitorsMissingSelection(
                    visitors = freshVisitors,
                    selectedVisitorIds = documents.keys
                ).isNotEmpty()
            ) {
                _uiState.value = _uiState.value.copy(
                    visitors = freshVisitors,
                    uploading = false,
                    error = "Choose a document for every traveller who still needs one."
                )
                return@launch
            }

            var currentVisitors = freshVisitors

            for (visitor in missing) {
                val document = documents[visitor.id] ?: continue

                when (val upload = repository.uploadVisitorDocument(
                    token = token,
                    bookingId = bookingId,
                    visitorId = visitor.id,
                    fileName = document.fileName,
                    mimeType = document.mimeType,
                    bytes = document.bytes
                )) {
                    is Resource.Success -> {
                        val uploaded = upload.data
                        currentVisitors = currentVisitors.map {
                            if (it.id == uploaded.id) uploaded else it
                        }
                        _uiState.value = _uiState.value.copy(visitors = currentVisitors)
                    }
                    is Resource.Error -> {
                        _uiState.value = _uiState.value.copy(
                            visitors = currentVisitors,
                            uploading = false,
                            error = "Upload stopped: ${upload.message}. " +
                                "Documents already accepted are kept; retry to finish the rest."
                        )
                        return@launch
                    }
                    else -> Unit
                }
            }

            val remaining = DocumentRecoveryRules.visitorsRequiringUpload(currentVisitors)
            if (remaining.isEmpty()) {
                recoveryStore.clear(bookingId, userId)
                _uiState.value = _uiState.value.copy(
                    visitors = currentVisitors,
                    uploading = false,
                    completed = true,
                    error = null
                )
            } else {
                _uiState.value = _uiState.value.copy(
                    visitors = currentVisitors,
                    uploading = false,
                    error = "Some documents are still missing. Retry to finish the rest."
                )
            }
        }
    }

    private fun applyAuthoritativeVisitors(visitors: List<Visitor>, userId: String) {
        val complete = visitors.isEmpty() ||
            DocumentRecoveryRules.visitorsRequiringUpload(visitors).isEmpty()

        if (complete) {
            recoveryStore.clear(bookingId, userId)
        }

        _uiState.value = _uiState.value.copy(
            isLoading = false,
            visitors = visitors,
            uploading = false,
            completed = complete,
            error = null
        )
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}
