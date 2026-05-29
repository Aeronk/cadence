<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'external_id',
        'signature_verified',
        'headers',
        'payload',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
