<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterDestinationRequest;
use App\Http\Resources\DestinationResource;
use App\Http\Resources\TourPackageResource;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\Wishlist;
use App\Services\DestinationFilter;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlists,
        private readonly DestinationFilter $filter,
    ) {}

    /**
     * Get all destinations.
     *
     * Supported optional query parameters: q, category, location, country,
     * min_price, max_price, min_rating, duration, available_date, sort,
     * page and per_page (1..50). Responses are always paginated with a
     * `meta` block; omitting `page` simply returns page 1 with 15 rows, which
     * keeps the legacy shape while making the paging explicit.
     */
    public function index(FilterDestinationRequest $request): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            $filters = $request->validated();

            $query = $this->filter->apply(Destination::query(), $filters);

            $destinations = $this->filter
                ->sort($query, $filters['sort'] ?? 'newest')
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount([
                    'publishedReviews as reviews_count',
                    'tourPackages',
                ])
                ->paginate((int) ($filters['per_page'] ?? 15));

            return response()->json([
                'success' => true,
                'data' => $destinations->getCollection()
                    ->map(fn (Destination $destination) => (new DestinationResource($destination))->resolve())
                    ->values(),
                'meta' => [
                    'current_page' => $destinations->currentPage(),
                    'last_page' => $destinations->lastPage(),
                    'per_page' => $destinations->perPage(),
                    'total' => $destinations->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Destinations index error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch destinations',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get popular destinations (for home screen)
     */
    public function popular(Request $request): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            // Get all destinations (without is_popular filter since column doesn't exist)
            // Just get the latest 6 destinations as "popular"
            $destinations = Destination::query()
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount([
                    'publishedReviews as reviews_count',
                    'tourPackages',
                ])
                ->latest()
                ->take($request->limit ?? 6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $destinations
                    ->map(fn (Destination $destination) => (new DestinationResource($destination))->resolve())
                    ->values(),
            ]);
        } catch (\Exception $e) {
            // Log the error and return empty array instead of 500 error
            \Log::error('Popular destinations error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular destinations',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get single destination
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            $destination = Destination::query()
                ->with([
                    'tourPackages' => fn ($query) => $query->withCount('bookings'),
                    'images',
                ])
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount([
                    'publishedReviews as reviews_count',
                    'tourPackages',
                ])
                ->findOrFail($id);

            $payload = (new DestinationResource($destination))->resolve();
            $payload['tour_packages'] = $destination->tourPackages
                ->map(fn (TourPackage $package) => (new TourPackageResource(
                    $package->setRelation('destination', $destination)
                ))->resolve())
                ->values();

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (\Exception $e) {
            \Log::error('Destination show error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Destination not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Load the current user's wishlist ids once per request. Resources read
     * them from request attributes, so a list response never runs a wishlist
     * query per item.
     *
     * The attributes are written to the container's `request` instance on
     * purpose: when a controller injects a FormRequest, Laravel resolves a
     * *copy* of the request, and JsonResource::resolve() reads the original.
     */
    private function shareWishlistIds(): void
    {
        $request = request();

        $user = $request->user();

        $request->attributes->set(
            'wishlisted_destination_ids',
            $this->wishlists->wishlistedIds($user, Wishlist::TYPE_DESTINATION)
        );
        $request->attributes->set(
            'wishlisted_tour_package_ids',
            $this->wishlists->wishlistedIds($user, Wishlist::TYPE_TOUR_PACKAGE)
        );
    }
}
