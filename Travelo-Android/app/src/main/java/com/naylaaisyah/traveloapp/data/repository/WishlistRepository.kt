package com.naylaaisyah.traveloapp.data.repository

import com.naylaaisyah.traveloapp.data.api.ApiService
import com.naylaaisyah.traveloapp.data.models.WishlistEntry
import com.naylaaisyah.traveloapp.data.models.WishlistToggleData
import com.naylaaisyah.traveloapp.data.models.WishlistToggleRequest
import com.naylaaisyah.traveloapp.util.Resource
import org.json.JSONObject
import retrofit2.HttpException
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Wishlist calls. Every request is authenticated and scoped to the current
 * traveller; the backend decides which rows exist and enforces ownership.
 */
@Singleton
class WishlistRepository @Inject constructor(
    private val apiService: ApiService
) {
    suspend fun getWishlist(
        token: String,
        type: String? = null,
        page: Int = 1,
        perPage: Int = 20
    ): Resource<List<WishlistEntry>> {
        return try {
            val response = apiService.getWishlist(
                token = "Bearer $token",
                type = type,
                page = page,
                perPage = perPage
            )
            val data = response.data
            if (response.success && data != null) {
                Resource.Success(data)
            } else {
                val message = response.message.ifBlank { "Couldn't load your favorites." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Couldn't load your favorites."
            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, "Couldn't load your favorites. Check your connection.")
        }
    }

    /**
     * Returns the server-confirmed state after toggling. The caller applies an
     * optimistic state first and rolls back only when this returns an error.
     */
    suspend fun toggle(
        token: String,
        itemType: String,
        itemId: Int
    ): Resource<Boolean> {
        return try {
            val response = apiService.toggleWishlist(
                "Bearer $token",
                WishlistToggleRequest(itemType = itemType, itemId = itemId)
            )
            val data: WishlistToggleData? = response.data
            if (response.success && data != null) {
                Resource.Success(data.isWishlisted)
            } else {
                val message = response.message.ifBlank { "Couldn't update your favorites." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Couldn't update your favorites."
            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, "Couldn't update your favorites. Check your connection.")
        }
    }

    suspend fun remove(token: String, wishlistId: Int): Resource<Unit> {
        return try {
            val response = apiService.deleteWishlist("Bearer $token", wishlistId)
            if (response.success) {
                Resource.Success(Unit)
            } else {
                val message = response.message.ifBlank { "Couldn't remove this favorite." }
                Resource.Error(Exception(message), message)
            }
        } catch (e: HttpException) {
            val message = serverMessage(e) ?: "Couldn't remove this favorite."
            Resource.Error(e, message)
        } catch (e: Exception) {
            Resource.Error(e, "Couldn't remove this favorite. Check your connection.")
        }
    }

    private fun serverMessage(e: HttpException): String? {
        val body = try {
            e.response()?.errorBody()?.string()
        } catch (_: Exception) {
            null
        }

        if (body.isNullOrBlank()) return null

        return try {
            JSONObject(body).optString("message").takeIf { it.isNotBlank() }
        } catch (_: Exception) {
            null
        }
    }
}
