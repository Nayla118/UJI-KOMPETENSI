<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the subject of the activity.
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that caused the activity.
     */
    public function causer()
    {
        return $this->morphTo();
    }

    /**
     * Log an activity.
     */
    public static function log(
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $causerType = null,
        ?int $causerId = null,
        ?array $properties = null
    ): self {
        return static::create([
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'properties' => $properties,
        ]);
    }

    /**
     * Scope for filtering by log name.
     */
    public function scopeForLog($query, string $logName)
    {
        return $query->where('log_name', $logName);
    }

    /**
     * Scope for recent activities.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
