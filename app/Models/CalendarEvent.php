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
        'all_day',
        'recurrence',
        'recurring_event_id',
        'organizer_email',
        'html_link',
        'conference_url',
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
            'all_day' => 'boolean',
            'attendees' => 'array',
            'recurrence' => 'array',
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

    /**
     * True when this row is the provider's copy of a meeting Cadence owns. Those
     * are rendered from the meeting itself, so the synced copy must not be drawn
     * a second time.
     */
    public function mirrorsLocalMeeting(): bool
    {
        return $this->meeting_id !== null;
    }

    /**
     * Attendee email addresses. Rows written before response status was tracked
     * hold bare strings, so both shapes are read.
     *
     * @return array<int, string>
     */
    public function attendeeEmails(): array
    {
        return collect($this->attendees ?? [])
            ->map(fn ($attendee) => is_array($attendee) ? ($attendee['email'] ?? null) : $attendee)
            ->filter()
            ->values()
            ->all();
    }
}
