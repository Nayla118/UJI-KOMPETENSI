<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourPackageItineraryDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => (int) $this->day_number,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
