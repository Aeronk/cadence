<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    public const TYPE_PHYSICAL = 'physical';

    public const TYPE_ONLINE = 'online';

    public const TYPE_HYBRID = 'hybrid';

    protected $fillable = [
        'workspace_id',
        'host_id',
        'project_id',
        'title',
        'description',
        'location',
        'meeting_url',
        'starts_at',
        'ends_at',
        'status',
        'meeting_type',
        'channel',
        'reminder_minutes_before',
        'reminder_sent_at',
        'recurrence_rule',
        'recurrence_ends_on',
        'conference_requested',
        'external_calendar_id',
        'external_event_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'reminder_minutes_before' => 'integer',
            'recurrence_ends_on' => 'date',
            'conference_requested' => 'boolean',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_user')
            ->withPivot('rsvp_status')
            ->withTimestamps();
    }

    /**
     * The task this meeting was scheduled from, if it came from one.
     */
    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_rule) && $this->recurrence_rule !== 'none';
    }

    /**
     * The meeting expressed as iCalendar recurrence lines, so a repeating
     * meeting becomes one provider event carrying an RRULE rather than one
     * event per occurrence.
     *
     * @return array<int, string>|null
     */
    public function recurrenceRules(): ?array
    {
        if (! $this->isRecurring()) {
            return null;
        }

        $frequency = match ($this->recurrence_rule) {
            'daily' => 'DAILY',
            'weekly' => 'WEEKLY',
            'monthly' => 'MONTHLY',
            'yearly' => 'YEARLY',
            default => null,
        };

        if (! $frequency) {
            return null;
        }

        $rule = 'RRULE:FREQ='.$frequency;

        if ($this->recurrence_ends_on) {
            // UNTIL must be UTC and inclusive of the whole final day.
            $rule .= ';UNTIL='.$this->recurrence_ends_on
                ->endOfDay()
                ->utc()
                ->format('Ymd\THis\Z');
        }

        return [$rule];
    }

    public function hasAttendee(User $user): bool
    {
        return $this->host_id === $user->id
            || $this->attendees()->where('users.id', $user->id)->exists();
    }
}
