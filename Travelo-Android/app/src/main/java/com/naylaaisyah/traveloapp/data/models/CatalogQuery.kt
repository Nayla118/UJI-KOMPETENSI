package com.naylaaisyah.traveloapp.data.models

import com.google.gson.annotations.SerializedName

/**
 * Pagination block returned by GET /api/destinations and
 * GET /api/tour-packages.
 */
data class CatalogMeta(
    @SerializedName("current_page")
    val currentPage: Int = 1,
    @SerializedName("last_page")
    val lastPage: Int = 1,
    @SerializedName("per_page")
    val perPage: Int = 10,
    val total: Int = 0
)

data class CatalogPage<T>(
    val items: List<T>,
    val meta: CatalogMeta
)

/**
 * Server-side query state for the Explore catalog. Keeping this in the data
 * layer (instead of building URL strings in the UI) makes the exact request
 * easy to unit test and impossible to drift between screens.
 */
data class CatalogQuery(
    val q: String? = null,
    val category: String? = null,
    val location: String? = null,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val minRating: Double? = null,
    val duration: String? = null,
    val availableDate: String? = null,
    val sort: String? = null,
    val page: Int = 1,
    val perPage: Int = 10
) {
    /**
     * Retrofit @QueryMap representation. Blank optional values are omitted so
     * the backend applies its defaults. `page` and `per_page` are always sent;
     * per_page is clamped to the API maximum of 50.
     */
    fun toQueryMap(): Map<String, String> = buildMap {
        put("page", page.coerceAtLeast(1).toString())
        put("per_page", perPage.coerceIn(1, MAX_PER_PAGE).toString())

        putIfNotBlank("q", q?.trim())
        putIfNotBlank("category", category)
        putIfNotBlank("location", location)
        putIfNotBlank("duration", duration)
        putIfNotBlank("available_date", availableDate)
        putIfNotBlank("sort", sort)

        minPrice?.let { put("min_price", it.toQueryNumber()) }
        maxPrice?.let { put("max_price", it.toQueryNumber()) }
        minRating?.let { put("min_rating", it.toQueryNumber()) }
    }

    private fun MutableMap<String, String>.putIfNotBlank(key: String, value: String?) {
        if (!value.isNullOrBlank()) put(key, value)
    }

    private fun Double.toQueryNumber(): String =
        if (this % 1.0 == 0.0) toLong().toString() else toString()

    companion object {
        const val MAX_PER_PAGE = 50
    }
}
