<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    public const TYPE_DESTINATION = 'destination';

    public const TYPE_TOUR_PACKAGE = 'tour_package';

    public const TYPES = [
        self::TYPE_DESTINATION,
        self::TYPE_TOUR_PACKAGE,
    ];

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDestination(): bool
    {
        return $this->item_type === self::TYPE_DESTINATION;
    }

    public function isTourPackage(): bool
    {
        return $this->item_type === self::TYPE_TOUR_PACKAGE;
    }
}
