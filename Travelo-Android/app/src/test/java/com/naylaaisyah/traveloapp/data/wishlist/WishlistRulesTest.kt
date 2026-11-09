package com.naylaaisyah.traveloapp.data.wishlist

import com.naylaaisyah.traveloapp.data.models.WishlistEntry
import com.naylaaisyah.traveloapp.data.models.WishlistTypes
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Pure JVM rules behind the optimistic heart. Keeping these separate from the
 * ViewModel lets us prove that a failed request restores the exact previous
 * state rather than blindly flipping the value back.
 */
class WishlistRulesTest {

    private val destination = WishlistKey(WishlistTypes.DESTINATION, 7)
    private val packageKey = WishlistKey(WishlistTypes.TOUR_PACKAGE, 11)

    // ---------- Optimistic toggle ----------

    @Test
    fun `toggle without an override flips the server value`() {
        val result = WishlistToggleState().optimisticToggle(destination, serverValue = false)

        assertTrue(result.nextValue)
        assertTrue(result.state.isWishlisted(destination, serverValue = false))
        assertEquals(null, result.previousOverride)
    }

    @Test
    fun `toggle removes an item that is already saved on the server`() {
        val result = WishlistToggleState().optimisticToggle(destination, serverValue = true)

        assertFalse(result.nextValue)
        assertFalse(result.state.isWishlisted(destination, serverValue = true))
    }

    @Test
    fun `toggle flips an existing optimistic override`() {
        val initial = WishlistToggleState().optimisticToggle(destination, serverValue = false)
        val second = initial.state.optimisticToggle(destination, serverValue = false)

        assertFalse(second.nextValue)
        assertEquals(true, second.previousOverride)
        assertFalse(second.state.isWishlisted(destination, serverValue = false))
    }

    @Test
    fun `overrides are scoped per item and type`() {
        val state = WishlistToggleState()
            .optimisticToggle(destination, serverValue = false).state
            .optimisticToggle(packageKey, serverValue = false).state

        assertTrue(state.isWishlisted(destination, serverValue = false))
        assertTrue(state.isWishlisted(packageKey, serverValue = false))

        val withoutDestination = state.withoutOverride(destination)

        assertFalse(withoutDestination.isWishlisted(destination, serverValue = false))
        assertTrue(withoutDestination.isWishlisted(packageKey, serverValue = false))
    }

    // ---------- Confirmation and rollback ----------

    @Test
    fun `confirm stores the server value`() {
        val update = WishlistToggleState().optimisticToggle(destination, serverValue = false)
        val confirmed = update.state.confirm(update, confirmed = true)

        assertTrue(confirmed.isWishlisted(destination, serverValue = false))
    }

    @Test
    fun `confirm can settle on a value that differs from the optimistic flip`() {
        val update = WishlistToggleState().optimisticToggle(destination, serverValue = false)
        val confirmed = update.state.confirm(update, confirmed = false)

        assertFalse(confirmed.isWishlisted(destination, serverValue = false))
    }

    @Test
    fun `rollback without a previous override falls back to the server value`() {
        val update = WishlistToggleState().optimisticToggle(destination, serverValue = false)
        val rolledBack = update.state.rollback(update)

        assertFalse(rolledBack.isWishlisted(destination, serverValue = false))
        assertEquals(emptyMap<WishlistKey, Boolean>(), rolledBack.overrides)
    }

    @Test
    fun `rollback restores the previous override after a double tap`() {
        val first = WishlistToggleState().optimisticToggle(destination, serverValue = false)
        val second = first.state.optimisticToggle(destination, serverValue = false)

        val rolledBack = second.state.rollback(second)

        assertTrue(rolledBack.isWishlisted(destination, serverValue = false))
        assertEquals(true, rolledBack.overrides[destination])
    }

    // ---------- Entry mapper ----------

    @Test
    fun `wishlist entries map to stable keys`() {
        val entries = listOf(
            entry(id = 1, type = WishlistTypes.DESTINATION, itemId = 7),
            entry(id = 2, type = WishlistTypes.TOUR_PACKAGE, itemId = 11)
        )

        assertEquals(setOf(destination, packageKey), entries.wishlistKeys())

        val first = entries.first()
        assertEquals(destination, first.wishlistKey())
        assertTrue(first.isDestination())
        assertFalse(first.isTourPackage())

        val second = entries.last()
        assertTrue(second.isTourPackage())
        assertFalse(second.isDestination())
    }

    @Test
    fun `keys separate the same numeric id across item types`() {
        val asDestination = WishlistKey(WishlistTypes.DESTINATION, 3)
        val asPackage = WishlistKey(WishlistTypes.TOUR_PACKAGE, 3)

        assertTrue(asDestination != asPackage)

        val state = WishlistToggleState().optimisticToggle(asDestination, serverValue = false).state

        assertTrue(state.isWishlisted(asDestination, serverValue = false))
        assertFalse(state.isWishlisted(asPackage, serverValue = false))
    }

    private fun entry(id: Int, type: String, itemId: Int) = WishlistEntry(
        id = id,
        itemType = type,
        itemId = itemId
    )
}
