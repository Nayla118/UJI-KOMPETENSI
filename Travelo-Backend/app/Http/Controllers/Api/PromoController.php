<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoController extends Controller
{
    public function __construct(protected PromoService $promos) {}

    /**
     * Preview a promo code. Everything (discount, total) is computed here;
     * the client only displays the result and sends the code back when the
     * booking is created.
     */
    public function validatePromo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:64',
            'tour_package_id' => 'required|integer|exists:tour_packages,id',
            'people' => 'required|integer|min:1',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = TourPackage::findOrFail($request->integer('tour_package_id'));

        if ($request->integer('people') > (int) $package->max_people) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum '.$package->max_people.' people allowed',
            ], 422);
        }

        $result = $this->promos->validate(
            (string) $request->input('code'),
            $request->user(),
            $package,
            $request->integer('people'),
            (string) $request->input('date')
        );

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'reason' => $result['reason'],
                'data' => [
                    'subtotal' => $result['subtotal'],
                    'discount' => 0,
                    'total' => $result['subtotal'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Promo code applied.',
            'data' => [
                'promo_code' => $result['code'],
                'subtotal' => $result['subtotal'],
                'discount' => $result['discount'],
                'total' => $result['total'],
                'discount_type' => $result['discount_type'],
                'discount_value' => $result['discount_value'],
                'max_discount' => $result['max_discount'],
            ],
        ]);
    }
}
