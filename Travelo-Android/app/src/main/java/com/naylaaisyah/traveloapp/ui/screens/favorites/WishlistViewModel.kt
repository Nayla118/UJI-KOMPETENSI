package com.naylaaisyah.traveloapp.ui.screens.favorites

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.data.models.WishlistEntry
import com.naylaaisyah.traveloapp.data.models.WishlistTypes
import com.naylaaisyah.traveloapp.data.repository.WishlistRepository
import com.naylaaisyah.traveloapp.data.wishlist.WishlistStore
import com.naylaaisyah.traveloapp.data.wishlist.WishlistToggleResult
import com.naylaaisyah.traveloapp.data.wishlist.WishlistToggleState
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class FavoritesUiState(
    val destinationEntries: List<WishlistEntry> = emptyList(),
    val packageEntries: List<WishlistEntry> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null
) {
    val isEmpty: Boolean
        get() = destinationEntries.isEmpty() && packageEntries.isEmpty()
}

sealed interface WishlistEvent {
    data object RequiresLogin : WishlistEvent

    data class Message(val text: String) : WishlistEvent
}

/**
 * One ViewModel for the Favorites screen and for heart buttons. Heart buttons
 * never call [loadFavorites], so catalog screens do not fetch the list unless
 * the traveller opens Favorit Saya.
 */
@HiltViewModel
class WishlistViewModel @Inject constructor(
    private val store: WishlistStore,
    private val repository: WishlistRepository,
    private val sessionManager: SessionManager
) : ViewModel() {

    val toggleState: StateFlow<WishlistToggleState> = store.state

    private val _favorites = MutableStateFlow(FavoritesUiState())
    val favorites: StateFlow<FavoritesUiState> = _favorites.asStateFlow()

    private val _events = MutableSharedFlow<WishlistEvent>(extraBufferCapacity = 8)
    val events: SharedFlow<WishlistEvent> = _events.asSharedFlow()

    fun loadFavorites() {
        val token = sessionManager.authToken?.takeIf { it.isNotBlank() }
        if (token == null) {
            _events.tryEmit(WishlistEvent.RequiresLogin)
            return
        }

        viewModelScope.launch {
            _favorites.value = _favorites.value.copy(isLoading = true, error = null)

            val destinations = repository.getWishlist(
                token = token,
                type = WishlistTypes.DESTINATION,
                perPage = 50
            )
            val packages = repository.getWishlist(
                token = token,
                type = WishlistTypes.TOUR_PACKAGE,
                perPage = 50
            )

            val error = when {
                destinations is Resource.Error -> destinations.message
                packages is Resource.Error -> packages.message
                else -> null
            }

            _favorites.value = FavoritesUiState(
                destinationEntries = (destinations as? Resource.Success<List<WishlistEntry>>)
                    ?.data.orEmpty(),
                packageEntries = (packages as? Resource.Success<List<WishlistEntry>>)
                    ?.data.orEmpty(),
                isLoading = false,
                error = error
            )
        }
    }

    fun toggle(itemType: String, itemId: Int, serverValue: Boolean) {
        viewModelScope.launch {
            when (val result = store.toggle(itemType, itemId, serverValue)) {
                is WishlistToggleResult.RequiresLogin ->
                    _events.emit(WishlistEvent.RequiresLogin)

                is WishlistToggleResult.Error ->
                    _events.emit(WishlistEvent.Message(result.message))

                is WishlistToggleResult.Success -> Unit
            }
        }
    }

    fun remove(entry: WishlistEntry) {
        viewModelScope.launch {
            when (val result = store.remove(entry.id, entry.itemType, entry.itemId)) {
                is WishlistToggleResult.RequiresLogin ->
                    _events.emit(WishlistEvent.RequiresLogin)

                is WishlistToggleResult.Error ->
                    _events.emit(WishlistEvent.Message(result.message))

                is WishlistToggleResult.Success -> {
                    _favorites.value = _favorites.value.copy(
                        destinationEntries = _favorites.value.destinationEntries
                            .filterNot { it.id == entry.id },
                        packageEntries = _favorites.value.packageEntries
                            .filterNot { it.id == entry.id }
                    )
                }
            }
        }
    }

    fun clearError() {
        _favorites.value = _favorites.value.copy(error = null)
    }
}
