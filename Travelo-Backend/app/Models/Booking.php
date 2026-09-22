<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tour_package_id',
        'booking_date',
        'people_count',
        'total_price',
        'payment_status',
        'booking_status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'booking_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
