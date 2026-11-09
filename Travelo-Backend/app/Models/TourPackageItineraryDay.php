<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourPackageItineraryDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'day_number',
        'title',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
