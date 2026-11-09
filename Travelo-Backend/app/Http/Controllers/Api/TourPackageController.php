<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterTourPackageRequest;
use App\Http\Resources\TourPackageResource;
use App\Models\TourPackage;
use App\Models\Wishlist;
use App\Services\TourPackageFilter;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourPackageController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlists,
        private readonly TourPackageFilter $filter,
    ) {}

    /**
     * Get all tour packages.
     *
     * Supported optional query parameters: q, category, location, country,
     * destination_id, min_price, max_price, min_rating, duration,
     * available_date, sort, page and per_page (1..50).
     */
    public function index(FilterTourPackageRequest $request): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            $filters = $request->validated();

            $query = $this->filter->apply(TourPackage::query(), $filters);

            $tourPackages = $this->filter
                ->sort($query, $filters['sort'] ?? 'newest')
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->withCount('bookings')
                ->paginate((int) ($filters['per_page'] ?? 15));

            return response()->json([
                'success' => true,
                'data' => $tourPackages->getCollection()
                    ->map(fn (TourPackage $package) => (new TourPackageResource($package))->resolve())
                    ->values(),
                'meta' => [
                    'current_page' => $tourPackages->currentPage(),
                    'last_page' => $tourPackages->lastPage(),
                    'per_page' => $tourPackages->perPage(),
                    'total' => $tourPackages->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Tour packages index error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tour packages',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get single tour package
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            $tourPackage = TourPackage::query()
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->with(['itineraryDays', 'images'])
                ->withCount('bookings')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => (new TourPackageResource($tourPackage))->resolve(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Tour package show error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Tour package not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get tour packages by destination
     */
    public function byDestination(int $destinationId, Request $request): JsonResponse
    {
        $this->shareWishlistIds();

        try {
            $tourPackages = TourPackage::query()
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->withCount('bookings')
                ->where('destination_id', $destinationId)
                ->latest()
                ->paginate(min(50, max(1, (int) ($request->per_page ?? 15))));

            return response()->json([
                'success' => true,
                'data' => $tourPackages->getCollection()
                    ->map(fn (TourPackage $package) => (new TourPackageResource($package))->resolve())
                    ->values(),
                'meta' => [
                    'current_page' => $tourPackages->currentPage(),
                    'last_page' => $tourPackages->lastPage(),
                    'per_page' => $tourPackages->perPage(),
                    'total' => $tourPackages->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Tour packages by destination error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tour packages',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Load the current user's wishlist ids once per request (see
     * DestinationController::shareWishlistIds). The container request is used
     * because a controller FormRequest is only a copy of it, while
     * JsonResource::resolve() reads the original instance.
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
