package com.naylaaisyah.traveloapp.data.wishlist

import com.naylaaisyah.traveloapp.data.repository.WishlistRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

sealed interface WishlistToggleResult {
    data class Success(val isWishlisted: Boolean) : WishlistToggleResult

    data class Error(val message: String) : WishlistToggleResult

    data object RequiresLogin : WishlistToggleResult
}

/**
 * App-wide wishlist override state. Screens observe the same singleton so a
 * heart toggled on Home is reflected on Explore and the detail screen without
 * refetching the catalog.
 */
@Singleton
class WishlistStore @Inject constructor(
    private val repository: WishlistRepository,
    private val sessionManager: SessionManager
) {
    private val _state = MutableStateFlow(WishlistToggleState())
    val state: StateFlow<WishlistToggleState> = _state.asStateFlow()

    private var activeUserKey: String? = null

    private fun syncUser() {
        val key = sessionManager.userId ?: sessionManager.authToken?.take(16)
        if (key != activeUserKey) {
            activeUserKey = key
            _state.value = WishlistToggleState()
        }
    }

    private fun authToken(): String? {
        syncUser()
        return sessionManager.authToken?.takeIf { it.isNotBlank() }
    }

    suspend fun toggle(
        itemType: String,
        itemId: Int,
        serverValue: Boolean
    ): WishlistToggleResult {
        val token = authToken() ?: return WishlistToggleResult.RequiresLogin
        val update = _state.value.optimisticToggle(
            WishlistKey(itemType, itemId),
            serverValue
        )
        _state.value = update.state

        return when (val result = repository.toggle(token, itemType, itemId)) {
            is Resource.Success -> {
                _state.value = _state.value.confirm(update, result.data)
                WishlistToggleResult.Success(result.data)
            }

            is Resource.Error -> {
                _state.value = _state.value.rollback(update)
                WishlistToggleResult.Error(
                    result.message.ifBlank { "Couldn't update your favorites." }
                )
            }

            is Resource.Loading -> {
                _state.value = _state.value.rollback(update)
                WishlistToggleResult.Error("Couldn't update your favorites.")
            }
        }
    }

    /** Remove a saved row (Favorites screen) with the same optimistic rules. */
    suspend fun remove(
        wishlistId: Int,
        itemType: String,
        itemId: Int
    ): WishlistToggleResult {
        val token = authToken() ?: return WishlistToggleResult.RequiresLogin
        val update = _state.value.optimisticToggle(
            WishlistKey(itemType, itemId),
            serverValue = true
        )
        _state.value = update.state

        return when (val result = repository.remove(token, wishlistId)) {
            is Resource.Success -> {
                _state.value = _state.value.confirm(update, false)
                WishlistToggleResult.Success(false)
            }

            is Resource.Error -> {
                _state.value = _state.value.rollback(update)
                WishlistToggleResult.Error(
                    result.message.ifBlank { "Couldn't remove this favorite." }
                )
            }

            is Resource.Loading -> {
                _state.value = _state.value.rollback(update)
                WishlistToggleResult.Error("Couldn't remove this favorite.")
            }
        }
    }
}
