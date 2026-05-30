<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSegment extends Model
{
    public const TYPE_FLIGHT = 'flight';

    public const TYPE_TRAIN = 'train';

    public const TYPE_DRIVE = 'drive';

    public const TYPE_HOTEL = 'hotel';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'trip_id',
        'type',
        'reference',
        'from_location',
        'to_location',
        'starts_at',
        'ends_at',
        'details',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
