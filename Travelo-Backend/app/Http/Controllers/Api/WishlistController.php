<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlists) {}

    /**
     * Paginated favourites, newest first, with a compact item summary.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(Wishlist::TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->wishlists->paginate(
            $request->user(),
            $validated['type'] ?? null,
            (int) ($validated['per_page'] ?? 15)
        );

        $summaries = $this->wishlists->summaries(collect($paginator->items()));

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())
                ->map(fn (Wishlist $wishlist) => [
                    'id' => $wishlist->id,
                    'item_type' => $wishlist->item_type,
                    'item_id' => $wishlist->item_id,
                    'item' => $summaries[$wishlist->id] ?? null,
                    'created_at' => $wishlist->created_at?->toIso8601String(),
                ])
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Add/remove a destination or package from the current user's favourites.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', Rule::in(Wishlist::TYPES)],
            'item_id' => ['required', 'integer', 'min:1'],
        ]);

        $isWishlisted = $this->wishlists->toggle(
            $request->user(),
            $validated['item_type'],
            (int) $validated['item_id']
        );

        return response()->json([
            'success' => true,
            'message' => $isWishlisted
                ? 'Ditambahkan ke favorit.'
                : 'Dihapus dari favorit.',
            'data' => [
                'is_wishlisted' => $isWishlisted,
            ],
        ]);
    }

    /**
     * Remove one favourite by wishlist id (owner only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->wishlists->deleteForUser($request->user(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Favorit tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dihapus dari favorit.',
            'data' => [
                'id' => $id,
            ],
        ]);
    }
}
