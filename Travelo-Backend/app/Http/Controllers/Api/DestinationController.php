<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DestinationController extends Controller
{
    /**
     * Get all destinations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Destination::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%");
                });
            }

            // Filter by country
            if ($request->has('country') && $request->country) {
                $query->where('country', $request->country);
            }

            $destinations = $query->latest()->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $destinations->items(),
                'meta' => [
                    'current_page' => $destinations->currentPage(),
                    'last_page' => $destinations->lastPage(),
                    'per_page' => $destinations->perPage(),
                    'total' => $destinations->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Destinations index error: ' . $e->getMessage());

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
        try {
            // Get all destinations (without is_popular filter since column doesn't exist)
            // Just get the latest 6 destinations as "popular"
            $destinations = Destination::latest()
                ->take($request->limit ?? 6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $destinations->map(function ($destination) {
                    return [
                        'id' => $destination->id,
                        'name' => $destination->name,
                        'city' => $destination->city,
                        'country' => $destination->country,
                        'description' => $destination->description,
                        'image' => $destination->image,
                        'rating' => $destination->rating,
                        'created_at' => $destination->created_at,
                        'updated_at' => $destination->updated_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            // Log the error and return empty array instead of 500 error
            \Log::error('Popular destinations error: ' . $e->getMessage());

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
    public function show(int $id): JsonResponse
    {
        try {
            $destination = Destination::with(['tourPackages'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $destination,
            ]);
        } catch (\Exception $e) {
            \Log::error('Destination show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Destination not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
