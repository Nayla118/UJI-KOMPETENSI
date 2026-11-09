<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit override for one tour package on one date.
 *
 * No row means "use tour_packages.max_people". A row with `is_closed` means
 * the date cannot be booked at all. `capacity` is the total seats for that
 * date, not the remaining seats; remaining is derived from active bookings.
 */
class TourPackageDateAvailability extends Model
{
    protected $fillable = [
        'tour_package_id',
        'date',
        'capacity',
        'is_closed',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'capacity' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
