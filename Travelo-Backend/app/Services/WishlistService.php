<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Traveller favourites (destinations and tour packages).
 *
 * The backend owns the list: a toggle validates that the referenced item
 * actually exists, and every lookup/delete is scoped to the owning user.
 */
class WishlistService
{
    /**
     * Add the item to the user's favourites, or remove it when it is already
     * saved. Returns the wishlist state after the change.
     */
    public function toggle(User $user, string $itemType, int $itemId): bool
    {
        $this->assertItemExists($itemType, $itemId);

        $existing = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        Wishlist::create([
            'user_id' => $user->id,
            'item_type' => $itemType,
            'item_id' => $itemId,
        ]);

        return true;
    }

    /**
     * Newest-first favourites for one user, optionally filtered by type.
     */
    public function paginate(User $user, ?string $itemType = null, int $perPage = 15): LengthAwarePaginator
    {
        return Wishlist::query()
            ->where('user_id', $user->id)
            ->when($itemType !== null, fn ($query) => $query->where('item_type', $itemType))
            ->latest()
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Wishlist ids for one item type. Public endpoints call this once per
     * request so a list of destinations/packages never causes N+1 queries.
     *
     * @return array<int, int>
     */
    public function wishlistedIds(?User $user, string $itemType): array
    {
        if ($user === null) {
            return [];
        }

        return Wishlist::query()
            ->where('user_id', $user->id)
            ->where('item_type', $itemType)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Build compact item summaries for a page of wishlist rows using two
     * batched queries (destinations and packages), never one query per row.
     *
     * @param  Collection<int, Wishlist>  $wishlists
     * @return array<int, array<string, mixed>> keyed by wishlist id
     */
    public function summaries(Collection $wishlists): array
    {
        $destinationIds = $wishlists
            ->where('item_type', Wishlist::TYPE_DESTINATION)
            ->pluck('item_id')
            ->unique()
            ->values()
            ->all();

        $packageIds = $wishlists
            ->where('item_type', Wishlist::TYPE_TOUR_PACKAGE)
            ->pluck('item_id')
            ->unique()
            ->values()
            ->all();

        $destinations = Destination::query()
            ->whereKey($destinationIds)
            ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
            ->withCount('publishedReviews as reviews_count')
            ->get()
            ->keyBy('id');

        $packages = TourPackage::query()
            ->whereKey($packageIds)
            ->with(['destination' => fn ($destination) => $destination
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount('publishedReviews as reviews_count')])
            ->get()
            ->keyBy('id');

        $summaries = [];

        foreach ($wishlists as $wishlist) {
            if ($wishlist->item_type === Wishlist::TYPE_DESTINATION) {
                $destination = $destinations->get($wishlist->item_id);
                $summaries[$wishlist->id] = $destination === null
                    ? null
                    : $this->destinationSummary($destination);
            } else {
                $package = $packages->get($wishlist->item_id);
                $summaries[$wishlist->id] = $package === null
                    ? null
                    : $this->packageSummary($package);
            }
        }

        return $summaries;
    }

    /**
     * Delete one favourite, but only when it belongs to the given user.
     */
    public function deleteForUser(User $user, int $wishlistId): bool
    {
        $wishlist = Wishlist::query()
            ->where('user_id', $user->id)
            ->find($wishlistId);

        if ($wishlist === null) {
            return false;
        }

        return (bool) $wishlist->delete();
    }

    public function itemExists(string $itemType, int $itemId): bool
    {
        return match ($itemType) {
            Wishlist::TYPE_DESTINATION => Destination::query()->whereKey($itemId)->exists(),
            Wishlist::TYPE_TOUR_PACKAGE => TourPackage::query()->whereKey($itemId)->exists(),
            default => false,
        };
    }

    private function assertItemExists(string $itemType, int $itemId): void
    {
        if (! in_array($itemType, Wishlist::TYPES, true)) {
            throw ValidationException::withMessages([
                'item_type' => ['The selected item type is invalid.'],
            ]);
        }

        if (! $this->itemExists($itemType, $itemId)) {
            throw ValidationException::withMessages([
                'item_id' => ['The selected item does not exist.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationSummary(Destination $destination): array
    {
        $reviewCount = (int) ($destination->reviews_count ?? 0);
        $rating = $destination->reviews_avg_rating !== null && $reviewCount > 0
            ? round((float) $destination->reviews_avg_rating, 2)
            : null;

        return [
            'id' => $destination->id,
            'type' => Wishlist::TYPE_DESTINATION,
            'name' => $destination->name,
            'city' => $destination->city,
            'country' => $destination->country,
            'image' => $destination->image_url,
            'price' => null,
            'price_formatted' => null,
            'rating' => $rating,
            'review_count' => $reviewCount,
            'duration_days' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSummary(TourPackage $package): array
    {
        $destination = $package->destination;
        $reviewCount = $destination !== null ? (int) ($destination->reviews_count ?? 0) : 0;
        $rating = $destination !== null
            && $destination->reviews_avg_rating !== null
            && $reviewCount > 0
            ? round((float) $destination->reviews_avg_rating, 2)
            : null;

        return [
            'id' => $package->id,
            'type' => Wishlist::TYPE_TOUR_PACKAGE,
            'name' => $package->title,
            'city' => $destination?->city,
            'country' => $destination?->country,
            'image' => $package->image_url,
            'price' => (float) $package->price,
            'price_formatted' => 'Rp '.number_format($package->price, 0, ',', '.'),
            'rating' => $rating,
            'review_count' => $reviewCount,
            'duration_days' => (int) $package->duration_days,
        ];
    }
}
