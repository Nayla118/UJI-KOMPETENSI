<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The owner's own review. Includes the moderation state so the app can show
 * "under review" without revealing another user's data.
 */
class MyReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'verified_visit' => (bool) $this->is_verified_visit,
            'moderation_reason' => $this->status === 'hidden'
                ? $this->moderation_reason
                : null,
            'destination' => $this->whenLoaded('destination', fn () => [
                'id' => $this->destination->id,
                'name' => $this->destination->name,
                'city' => $this->destination->city,
                'country' => $this->destination->country,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
