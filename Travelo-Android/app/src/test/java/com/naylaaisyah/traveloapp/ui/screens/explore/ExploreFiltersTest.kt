package com.naylaaisyah.traveloapp.ui.screens.explore

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Explore filters are pure state: the sheet edits them, the ViewModel turns
 * them into an API query, and the badge counts them. None of that needs
 * Android, so it can be verified on the JVM.
 */
class ExploreFiltersTest {

    @Test
    fun `default filters are inactive`() {
        val filters = ExploreFilters()

        assertFalse(filters.isActive)
        assertEquals(0, filters.activeCount)
        assertEquals(SortOption.RECOMMENDED, filters.sortBy)
    }

    @Test
    fun `each filter group contributes one to the badge`() {
        val all = ExploreFilters(
            category = "pantai",
            minPrice = 500000.0,
            maxPrice = 2000000.0,
            minRating = 4.5,
            duration = "2-3",
            availableDate = "2026-11-20",
            sortBy = SortOption.POPULAR
        )

        // Price min+max is one group, not two, and sort counts once.
        assertEquals(6, all.activeCount)
        assertTrue(all.isActive)
    }

    @Test
    fun `price range counts once regardless of which end is set`() {
        assertEquals(1, ExploreFilters(minPrice = 500000.0).activeCount)
        assertEquals(1, ExploreFilters(maxPrice = 500000.0).activeCount)
        assertEquals(1, ExploreFilters(minPrice = 500000.0, maxPrice = 900000.0).activeCount)
    }

    @Test
    fun `recommended sort does not count as an active filter`() {
        assertEquals(0, ExploreFilters(sortBy = SortOption.RECOMMENDED).activeCount)
        assertEquals(1, ExploreFilters(sortBy = SortOption.NEWEST).activeCount)
        assertEquals(1, ExploreFilters(sortBy = SortOption.PRICE_LOW_HIGH).activeCount)
    }

    @Test
    fun `filters are mapped onto the catalog query`() {
        val query = ExploreFilters(
            category = "gunung",
            minPrice = 800000.0,
            maxPrice = 3500000.0,
            minRating = 4.0,
            duration = "4+",
            availableDate = "2026-12-01",
            sortBy = SortOption.RATING
        ).toCatalogQuery(searchQuery = "bromo", page = 3, perPage = 10)

        assertEquals("bromo", query.q)
        assertEquals("gunung", query.category)
        assertEquals(800000.0, query.minPrice)
        assertEquals(3500000.0, query.maxPrice)
        assertEquals(4.0, query.minRating)
        assertEquals("4+", query.duration)
        assertEquals("2026-12-01", query.availableDate)
        assertEquals("rating_desc", query.sort)
        assertEquals(3, query.page)
        assertEquals(10, query.perPage)
    }

    @Test
    fun `blank search and recommended sort are not sent`() {
        val query = ExploreFilters().toCatalogQuery(searchQuery = "   ", page = 1, perPage = 10)

        assertNull(query.q)
        assertNull(query.sort)
        assertNull(query.category)
        assertNull(query.duration)
    }

    @Test
    fun `search text is trimmed before it reaches the query`() {
        val query = ExploreFilters().toCatalogQuery(searchQuery = "  ubud ", page = 1, perPage = 10)

        assertEquals("ubud", query.q)
    }

    @Test
    fun `every sort option maps to the backend value`() {
        val expected = mapOf(
            SortOption.RECOMMENDED to null,
            SortOption.POPULAR to "popular",
            SortOption.NEWEST to "newest",
            SortOption.RATING to "rating_desc",
            SortOption.PRICE_LOW_HIGH to "price_asc",
            SortOption.PRICE_HIGH_LOW to "price_desc"
        )

        expected.forEach { (option, apiValue) ->
            val query = ExploreFilters(sortBy = option)
                .toCatalogQuery(searchQuery = "", page = 1, perPage = 10)

            assertEquals(apiValue, query.sort)
        }
    }

    @Test
    fun `duration options stay in the format the API validates`() {
        assertEquals(listOf("1", "2-3", "4+"), ExploreFilters.DURATIONS)

        ExploreFilters.DURATIONS.forEach { duration ->
            val query = ExploreFilters(duration = duration)
                .toCatalogQuery(searchQuery = "", page = 1, perPage = 1)

            assertEquals(duration, query.duration)
        }
    }

    @Test
    fun `labels are human readable versions of the api values`() {
        assertEquals("Beach", ExploreFilters.categoryLabel("pantai"))
        assertEquals("Mountain", ExploreFilters.categoryLabel("gunung"))
        assertEquals("Culture", ExploreFilters.categoryLabel("budaya"))
        assertEquals("Culinary", ExploreFilters.categoryLabel("kuliner"))
        assertEquals("Nature", ExploreFilters.categoryLabel("alam"))
        assertEquals("City", ExploreFilters.categoryLabel("kota"))
        assertEquals("1 day", ExploreFilters.durationLabel("1"))
        assertEquals("2-3 days", ExploreFilters.durationLabel("2-3"))
        assertEquals("4+ days", ExploreFilters.durationLabel("4+"))
    }

    @Test
    fun `reset is represented by an empty filter instance`() {
        val query = ExploreFilters().toCatalogQuery(searchQuery = "", page = 1, perPage = 10)

        assertFalse(ExploreFilters().isActive)
        assertEquals("1", query.toQueryMap()["page"])
        assertEquals("10", query.toQueryMap()["per_page"])
        assertEquals(2, query.toQueryMap().size)
    }
}
