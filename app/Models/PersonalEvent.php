<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PersonalEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalEvent extends Model
{
    /** @use HasFactory<PersonalEventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'title',
        'category',
        'event_date',
        'recurs_yearly',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'recurs_yearly' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns every occurrence date in the given range.
     * Non-recurring events surface once if the date falls inside.
     * Recurring-yearly events surface once per year inside the range.
     *
     * @return array<int, string> ISO dates
     */
    public function occurrencesIn(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->recurs_yearly) {
            return $this->event_date->between($start, $end)
                ? [$this->event_date->toDateString()]
                : [];
        }

        $dates = [];
        for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
            $candidate = $this->event_date->setYear($year);
            if ($candidate->greaterThanOrEqualTo($start) && $candidate->lessThanOrEqualTo($end)) {
                $dates[] = $candidate->toDateString();
            }
        }

        return $dates;
    }
}
