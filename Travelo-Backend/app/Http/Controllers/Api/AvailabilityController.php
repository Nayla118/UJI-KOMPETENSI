<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $availability) {}

    /**
     * Public month view for one tour package.
     *
     * GET /api/tour-packages/{id}/availability?month=YYYY-MM&people=2
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $package = TourPackage::query()->findOrFail($id);

        $validator = validator([
            'month' => $request->query('month', now()->format('Y-m')),
            'people' => $request->query('people', 1),
        ], [
            'month' => ['required', 'date_format:Y-m'],
            'people' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->availability->forMonth(
            $package,
            (string) $validator->validated()['month'],
            (int) ($validator->validated()['people'] ?? 1)
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
