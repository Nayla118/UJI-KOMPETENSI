package com.naylaaisyah.traveloapp.ui.screens.destination

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.Destination
import com.naylaaisyah.traveloapp.data.models.Review
import com.naylaaisyah.traveloapp.data.models.ReviewEligibility
import com.naylaaisyah.traveloapp.data.models.ReviewMeta
import com.naylaaisyah.traveloapp.data.models.ReviewSummary
import com.naylaaisyah.traveloapp.data.models.TourPackage
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DestinationDetailUiState(
    val destination: Destination? = null,
    val tourPackages: List<TourPackage> = emptyList(),
    val isLoading: Boolean = true,
    val error: String? = null,
    val toursError: String? = null,
    val reviewSummary: ReviewSummary = ReviewSummary(),
    val reviews: List<Review> = emptyList(),
    val reviewMeta: ReviewMeta = ReviewMeta(),
    val isReviewsLoading: Boolean = false,
    val reviewsError: String? = null,
    val isLoadingMoreReviews: Boolean = false,
    val loadMoreReviewsError: String? = null,
    val eligibility: ReviewEligibility? = null,
    val isEligibilityLoading: Boolean = false,
    val isSavingReview: Boolean = false,
    val reviewActionError: String? = null,
    val reviewSaved: Boolean = false
)

@HiltViewModel
class DestinationDetailViewModel @Inject constructor(
    private val repository: TraveloRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(DestinationDetailUiState())
    val uiState: StateFlow<DestinationDetailUiState> = _uiState.asStateFlow()

    fun loadDestination(id: Int) {
        viewModelScope.launch {
            _uiState.value = DestinationDetailUiState(isLoading = true)

            when (val result = repository.getDestinationDetail(id)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        destination = result.data,
                        error = null
                    )
                    // Keep the sections independent: a slow review request must
                    // never hide the destination or its tour packages.
                    launch { loadTourPackages(id) }
                    launch { loadReviews(id) }
                    launch { loadEligibility(id) }
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun retryTourPackages() {
        val destinationId = _uiState.value.destination?.id ?: return
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, toursError = null)
            loadTourPackages(destinationId)
        }
    }

    fun retryReviews() {
        val destinationId = _uiState.value.destination?.id ?: return
        viewModelScope.launch {
            loadReviews(destinationId)
            loadEligibility(destinationId)
        }
    }

    /**
     * Append the next page of public reviews. The first page keeps its own
     * loading/error state so a failed "show more" request never hides reviews
     * that were already loaded.
     */
    fun loadMoreReviews() {
        val state = _uiState.value
        val destinationId = state.destination?.id ?: return
        val nextPage = state.reviewMeta.currentPage + 1

        if (state.isReviewsLoading ||
            state.isLoadingMoreReviews ||
            nextPage > state.reviewMeta.lastPage
        ) {
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isLoadingMoreReviews = true,
                loadMoreReviewsError = null
            )

            when (val result = repository.getDestinationReviews(destinationId, page = nextPage)) {
                is Resource.Success -> {
                    val data = result.data
                    val current = _uiState.value
                    val merged = (current.reviews + data.reviews).distinctBy { it.id }
                    val destination = current.destination?.copy(
                        rating = data.summary.average,
                        reviewCount = data.summary.count
                    )

                    _uiState.value = current.copy(
                        destination = destination,
                        reviewSummary = data.summary,
                        reviews = merged,
                        reviewMeta = data.meta,
                        isLoadingMoreReviews = false,
                        loadMoreReviewsError = null
                    )
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoadingMoreReviews = false,
                        loadMoreReviewsError = result.message
                    )
                }

                is Resource.Loading -> Unit
            }
        }
    }

    fun saveReview(rating: Int, comment: String?) {
        val token = sessionManager.authToken

        if (token.isNullOrBlank()) {
            _uiState.value = _uiState.value.copy(
                reviewActionError = "Your session has expired. Please sign in again."
            )
            return
        }

        val eligibility = _uiState.value.eligibility ?: return
        val existing = eligibility.review
        val destinationId = eligibility.destinationId

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isSavingReview = true,
                reviewActionError = null,
                reviewSaved = false
            )

            val result = if (existing != null && existing.isPublished) {
                repository.updateReview(token, existing.id, rating, comment)
            } else if (eligibility.canReview && eligibility.bookingId != null) {
                repository.submitReview(token, eligibility.bookingId, rating, comment)
            } else {
                null
            }

            when (result) {
                is Resource.Success -> {
                    // Apply the saved review immediately. If a refresh is slow
                    // or fails, the rate button must not come back for a visit
                    // that has already been reviewed.
                    val saved = result.data
                    val updatedEligibility = _uiState.value.eligibility?.copy(
                        status = "reviewed",
                        canReview = false,
                        review = saved
                    )

                    _uiState.value = _uiState.value.copy(
                        eligibility = updatedEligibility,
                        isSavingReview = false,
                        reviewSaved = true,
                        reviewActionError = null
                    )
                    loadReviews(destinationId)
                    loadEligibility(destinationId)
                    // Tour packages inherit the destination aggregate.
                    loadTourPackages(destinationId)
                }

                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isSavingReview = false,
                        reviewActionError = result.message
                    )
                }

                else -> {
                    _uiState.value = _uiState.value.copy(
                        isSavingReview = false,
                        reviewActionError = "This visit is not eligible for a review."
                    )
                }
            }
        }
    }

    fun clearReviewActionError() {
        _uiState.value = _uiState.value.copy(reviewActionError = null)
    }

    fun consumeReviewSaved() {
        _uiState.value = _uiState.value.copy(reviewSaved = false)
    }

    private suspend fun loadTourPackages(id: Int) {
        when (val result = repository.getTourPackagesByDestination(id)) {
            is Resource.Success -> {
                _uiState.value = _uiState.value.copy(
                    tourPackages = result.data,
                    toursError = null,
                    isLoading = false
                )
            }

            is Resource.Error -> {
                _uiState.value = _uiState.value.copy(
                    toursError = result.message,
                    isLoading = false
                )
            }

            is Resource.Loading -> Unit
        }
    }

    private suspend fun loadReviews(id: Int) {
        _uiState.value = _uiState.value.copy(
            isReviewsLoading = true,
            reviewsError = null
        )

        when (val result = repository.getDestinationReviews(id)) {
            is Resource.Success -> {
                val data = result.data
                val destination = _uiState.value.destination?.copy(
                    rating = data.summary.average,
                    reviewCount = data.summary.count
                )
                _uiState.value = _uiState.value.copy(
                    destination = destination,
                    reviewSummary = data.summary,
                    reviews = data.reviews,
                    reviewMeta = data.meta,
                    isReviewsLoading = false,
                    reviewsError = null,
                    loadMoreReviewsError = null
                )
            }

            is Resource.Error -> {
                _uiState.value = _uiState.value.copy(
                    isReviewsLoading = false,
                    reviewsError = result.message
                )
            }

            is Resource.Loading -> Unit
        }
    }

    private suspend fun loadEligibility(id: Int) {
        val token = sessionManager.authToken ?: return

        _uiState.value = _uiState.value.copy(isEligibilityLoading = true)

        when (val result = repository.getReviewEligibility(token, id)) {
            is Resource.Success -> {
                _uiState.value = _uiState.value.copy(
                    eligibility = result.data,
                    isEligibilityLoading = false
                )
            }

            is Resource.Error -> {
                _uiState.value = _uiState.value.copy(
                    isEligibilityLoading = false
                )
            }

            is Resource.Loading -> Unit
        }
    }
}
