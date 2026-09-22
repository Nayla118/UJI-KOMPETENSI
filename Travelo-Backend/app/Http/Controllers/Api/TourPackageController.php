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
            $query = TourPackage::with('destination');

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
                'data' => $tourPackages->items(),
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
            $tourPackage = TourPackage::with(['destination', 'bookings'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tourPackage,
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
            $tourPackages = TourPackage::with('destination')
                ->where('destination_id', $destinationId)
                ->latest()
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $tourPackages->items(),
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
