<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'integration_account_id',
        'meeting_id',
        'external_id',
        'etag',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'attendees',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attendees' => 'array',
        ];
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
