package com.naylaaisyah.traveloapp.data.wishlist

import com.naylaaisyah.traveloapp.data.models.WishlistEntry
import com.naylaaisyah.traveloapp.data.models.WishlistTypes

/** Stable identity for a wishlist item across screens. */
data class WishlistKey(
    val itemType: String,
    val itemId: Int
)

/**
 * Client-side wishlist overrides.
 *
 * Server responses remain the source of truth (`serverValue`), while an
 * override records an optimistic toggle that has not necessarily been
 * confirmed yet. Rolling back simply removes the override.
 */
data class WishlistToggleState(
    val overrides: Map<WishlistKey, Boolean> = emptyMap()
) {
    fun isWishlisted(key: WishlistKey, serverValue: Boolean): Boolean =
        overrides[key] ?: serverValue

    fun withOverride(key: WishlistKey, value: Boolean): WishlistToggleState =
        copy(overrides = overrides + (key to value))

    fun withoutOverride(key: WishlistKey): WishlistToggleState =
        copy(overrides = overrides - key)
}

/** Snapshot needed to either confirm or roll back one optimistic toggle. */
data class OptimisticWishlistToggle(
    val state: WishlistToggleState,
    val key: WishlistKey,
    val previousOverride: Boolean?,
    val nextValue: Boolean
)

fun WishlistToggleState.optimisticToggle(
    key: WishlistKey,
    serverValue: Boolean
): OptimisticWishlistToggle {
    val previous = overrides[key]
    val next = !isWishlisted(key, serverValue)

    return OptimisticWishlistToggle(
        state = withOverride(key, next),
        key = key,
        previousOverride = previous,
        nextValue = next
    )
}

fun WishlistToggleState.confirm(update: OptimisticWishlistToggle, confirmed: Boolean): WishlistToggleState =
    withOverride(update.key, confirmed)

fun WishlistToggleState.rollback(update: OptimisticWishlistToggle): WishlistToggleState =
    if (update.previousOverride == null) {
        withoutOverride(update.key)
    } else {
        withOverride(update.key, update.previousOverride)
    }

fun WishlistEntry.wishlistKey(): WishlistKey = WishlistKey(itemType, itemId)

fun List<WishlistEntry>.wishlistKeys(): Set<WishlistKey> =
    map { it.wishlistKey() }.toSet()

fun WishlistEntry.isDestination(): Boolean = itemType == WishlistTypes.DESTINATION

fun WishlistEntry.isTourPackage(): Boolean = itemType == WishlistTypes.TOUR_PACKAGE
