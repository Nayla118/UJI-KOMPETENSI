<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewCount = $this->reviewCount();
        $average = $this->reviews_avg_rating !== null && $reviewCount > 0
            ? round((float) $this->reviews_avg_rating, 2)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'country' => $this->country,
            'description' => $this->description,
            'image' => $this->imageUrl,
            'image_path' => $this->image,
            // Legacy destinations.rating is never trusted as a review rating.
            'rating' => $average,
            'review_count' => $reviewCount,
            'is_popular' => (bool) $this->is_popular,
            'tour_packages_count' => $this->whenCounted('tourPackages', $this->tourPackages_count ?? $this->tourPackages->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function reviewCount(): int
    {
        if (array_key_exists('reviews_count', $this->resource->getAttributes())) {
            return (int) $this->reviews_count;
        }

        if ($this->relationLoaded('publishedReviews')) {
            return $this->publishedReviews->count();
        }

        return 0;
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
