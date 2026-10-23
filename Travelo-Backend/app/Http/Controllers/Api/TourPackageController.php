<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourPackageResource;
use App\Models\TourPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TourPackageController extends Controller
{
    /**
     * Get all tour packages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TourPackage::query()
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->withCount('bookings');

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by destination
            if ($request->has('destination_id') && $request->destination_id) {
                $query->where('destination_id', $request->destination_id);
            }

            $tourPackages = $query->latest()->paginate($request->per_page ?? 15);

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
            \Log::error('Tour packages index error: ' . $e->getMessage());

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
    public function show(int $id): JsonResponse
    {
        try {
            $tourPackage = TourPackage::query()
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->withCount('bookings')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => (new TourPackageResource($tourPackage))->resolve(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Tour package show error: ' . $e->getMessage());

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
        try {
            $tourPackages = TourPackage::query()
                ->with(['destination' => fn ($destination) => $destination
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count')])
                ->withCount('bookings')
                ->where('destination_id', $destinationId)
                ->latest()
                ->paginate($request->per_page ?? 15);

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
            \Log::error('Tour packages by destination error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tour packages',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
