<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register or refresh the FCM token for the current device.
     *
     * The token is globally unique. Re-registering it on a shared device
     * moves it to the user who is currently signed in, so push never reaches
     * the previous traveller.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registered for notifications.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
            ],
        ], 201);
    }

    /**
     * Remove a token on logout or when the app no longer wants push.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device unregistered from notifications.',
        ]);
    }
}
