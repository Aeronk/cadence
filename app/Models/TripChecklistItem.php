<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripChecklistItem extends Model
{
    protected $fillable = [
        'trip_id',
        'title',
        'checked',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'checked' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
