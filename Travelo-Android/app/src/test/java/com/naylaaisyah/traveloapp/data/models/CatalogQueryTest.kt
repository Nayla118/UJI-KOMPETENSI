package com.naylaaisyah.traveloapp.data.models

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The Explore catalog is filtered, sorted and paginated by the backend, so the
 * query map is the contract with the API. These tests pin the parameter names,
 * the omitted defaults and the page-size limit.
 */
class CatalogQueryTest {

    @Test
    fun `default query only sends paging parameters`() {
        val map = CatalogQuery().toQueryMap()

        assertEquals(
            mapOf("page" to "1", "per_page" to "10"),
            map
        )
    }

    @Test
    fun `every filter is sent with the documented parameter name`() {
        val map = CatalogQuery(
            q = "bali",
            category = "pantai",
            location = "Denpasar",
            minPrice = 500000.0,
            maxPrice = 2500000.0,
            minRating = 4.5,
            duration = "4+",
            availableDate = "2026-11-20",
            sort = "price_asc",
            page = 2,
            perPage = 20
        ).toQueryMap()

        assertEquals("bali", map["q"])
        assertEquals("pantai", map["category"])
        assertEquals("Denpasar", map["location"])
        assertEquals("500000", map["min_price"])
        assertEquals("2500000", map["max_price"])
        assertEquals("4.5", map["min_rating"])
        assertEquals("4+", map["duration"])
        assertEquals("2026-11-20", map["available_date"])
        assertEquals("price_asc", map["sort"])
        assertEquals("2", map["page"])
        assertEquals("20", map["per_page"])
    }

    @Test
    fun `blank optional values are omitted instead of sent as empty`() {
        val map = CatalogQuery(
            q = "   ",
            category = "",
            location = null,
            duration = " ",
            sort = ""
        ).toQueryMap()

        assertFalse(map.containsKey("q"))
        assertFalse(map.containsKey("category"))
        assertFalse(map.containsKey("location"))
        assertFalse(map.containsKey("duration"))
        assertFalse(map.containsKey("sort"))
    }

    @Test
    fun `search term is trimmed before it is sent`() {
        val map = CatalogQuery(q = "  lombok  ").toQueryMap()

        assertEquals("lombok", map["q"])
    }

    @Test
    fun `whole prices are sent without a decimal suffix`() {
        val map = CatalogQuery(minPrice = 1000000.0, maxPrice = 1500000.0).toQueryMap()

        assertEquals("1000000", map["min_price"])
        assertEquals("1500000", map["max_price"])
    }

    @Test
    fun `fractional prices keep their decimal part`() {
        val map = CatalogQuery(minPrice = 1000000.5).toQueryMap()

        assertEquals("1000000.5", map["min_price"])
    }

    @Test
    fun `page size is clamped to the backend maximum of fifty`() {
        assertEquals("50", CatalogQuery(perPage = 500).toQueryMap()["per_page"])
        assertEquals("1", CatalogQuery(perPage = 0).toQueryMap()["per_page"])
        assertEquals("1", CatalogQuery(page = 0).toQueryMap()["page"])
    }

    @Test
    fun `unknown sort or duration values are still forwarded for server validation`() {
        val map = CatalogQuery(sort = "cheapest", duration = "7").toQueryMap()

        assertEquals("cheapest", map["sort"])
        assertEquals("7", map["duration"])
    }

    @Test
    fun `meta defaults keep a single page when the backend omits it`() {
        val meta = CatalogMeta()

        assertEquals(1, meta.currentPage)
        assertEquals(1, meta.lastPage)
        assertEquals(10, meta.perPage)
        assertEquals(0, meta.total)
    }

    @Test
    fun `paged result keeps items and meta together`() {
        val page = CatalogPage(
            items = listOf("one", "two"),
            meta = CatalogMeta(currentPage = 2, lastPage = 3, perPage = 2, total = 6)
        )

        assertEquals(2, page.items.size)
        assertTrue(page.meta.currentPage < page.meta.lastPage)
        assertEquals(6, page.meta.total)
    }
}
