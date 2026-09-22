<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'destination_id' => $this->destination_id,
            'destination' => new DestinationResource($this->whenLoaded('destination')),
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'price_formatted' => 'Rp ' . number_format($this->price, 0, ',', '.'),
            'duration_days' => (int) $this->duration_days,
            'max_people' => (int) $this->max_people,
            'image' => $this->imageUrl,
            'image_path' => $this->image,
            'rating' => (float) $this->rating,
            'bookings_count' => $this->whenCounted('bookings', $this->bookings_count ?? $this->bookings->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
