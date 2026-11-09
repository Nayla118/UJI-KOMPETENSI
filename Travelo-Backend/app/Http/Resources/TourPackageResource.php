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
        $destination = $this->relationLoaded('destination') ? $this->destination : null;
        $reviewCount = $destination !== null
            ? (int) ($destination->reviews_count ?? 0)
            : 0;
        $average = $destination !== null
            && $destination->reviews_avg_rating !== null
            && $reviewCount > 0
            ? round((float) $destination->reviews_avg_rating, 2)
            : null;

        return [
            'id' => $this->id,
            'destination_id' => $this->destination_id,
            'destination' => new DestinationResource($this->whenLoaded('destination')),
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'price_formatted' => 'Rp '.number_format($this->price, 0, ',', '.'),
            'duration_days' => (int) $this->duration_days,
            'max_people' => (int) $this->max_people,
            'image' => $this->imageUrl,
            'image_path' => $this->image,
            'inclusions' => array_values((array) ($this->inclusions ?? [])),
            'exclusions' => array_values((array) ($this->exclusions ?? [])),
            'meeting_point' => $this->meeting_point,
            'meeting_latitude' => $this->meeting_latitude !== null ? (float) $this->meeting_latitude : null,
            'meeting_longitude' => $this->meeting_longitude !== null ? (float) $this->meeting_longitude : null,
            'terms' => $this->terms,
            // Only eager-loaded by the detail endpoint, so list responses stay
            // free of N+1 gallery/itinerary queries.
            'itinerary' => TourPackageItineraryDayResource::collection(
                $this->whenLoaded('itineraryDays')
            ),
            'images' => TourPackageImageResource::collection(
                $this->whenLoaded('images')
            ),
            // A package inherits the published aggregate of its destination.
            'rating' => $average,
            'review_count' => $reviewCount,
            // Avoid lazy-loading bookings when the controller did not
            // withCount them; list responses must stay N+1 free.
            'bookings_count' => (int) (
                $this->bookings_count
                ?? ($this->relationLoaded('bookings') ? $this->bookings->count() : 0)
            ),
            'is_wishlisted' => $this->when(
                $request->user() !== null,
                fn () => in_array(
                    $this->id,
                    (array) $request->attributes->get('wishlisted_tour_package_ids', []),
                    true
                )
            ),
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
