package com.naylaaisyah.traveloapp.ui.screens.explore

import com.naylaaisyah.traveloapp.data.models.CatalogQuery

/**
 * Sort values understood by GET /api/destinations and /api/tour-packages.
 * `RECOMMENDED` sends no sort parameter, which lets the backend use its
 * documented default (newest).
 */
enum class SortOption(val apiValue: String?, val label: String) {
    RECOMMENDED(null, "Recommended"),
    POPULAR("popular", "Popular"),
    NEWEST("newest", "Newest"),
    RATING("rating_desc", "Top rated"),
    PRICE_LOW_HIGH("price_asc", "Price: low to high"),
    PRICE_HIGH_LOW("price_desc", "Price: high to low")
}

/**
 * Pure state used to build the backend query. It intentionally contains no
 * Android dependencies so the request builder is unit-testable.
 */
data class ExploreFilters(
    val category: String? = null,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val minRating: Double? = null,
    val duration: String? = null,
    val availableDate: String? = null,
    val sortBy: SortOption = SortOption.RECOMMENDED
) {
    val activeCount: Int
        get() = listOf(
            category != null,
            minPrice != null || maxPrice != null,
            minRating != null,
            duration != null,
            availableDate != null,
            sortBy != SortOption.RECOMMENDED
        ).count { it }

    val isActive: Boolean get() = activeCount > 0

    fun toCatalogQuery(
        searchQuery: String,
        page: Int,
        perPage: Int
    ): CatalogQuery = CatalogQuery(
        q = searchQuery.trim().takeIf { it.isNotEmpty() },
        category = category,
        minPrice = minPrice,
        maxPrice = maxPrice,
        minRating = minRating,
        duration = duration,
        availableDate = availableDate,
        sort = sortBy.apiValue,
        page = page,
        perPage = perPage
    )

    companion object {
        val CATEGORIES = listOf("pantai", "gunung", "budaya", "kuliner", "alam", "kota")

        val DURATIONS = listOf("1", "2-3", "4+")

        val RATINGS = listOf(4.0, 4.5)

        const val MAX_PRICE = 10_000_000.0

        fun categoryLabel(value: String): String = when (value) {
            "pantai" -> "Beach"
            "gunung" -> "Mountain"
            "budaya" -> "Culture"
            "kuliner" -> "Culinary"
            "alam" -> "Nature"
            "kota" -> "City"
            else -> value.replaceFirstChar { it.uppercase() }
        }

        fun durationLabel(value: String): String = when (value) {
            "1" -> "1 day"
            "2-3" -> "2-3 days"
            "4+" -> "4+ days"
            else -> value
        }
    }
}
