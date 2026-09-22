<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One calendar belonging to a connected account — an entry of Google's
 * CalendarList or of Graph's /me/calendars.
 */
class CalendarSource extends Model
{
    use BelongsToWorkspace;

    /** Roles that allow Cadence to write an event into the calendar. */
    public const WRITABLE_ROLES = ['owner', 'writer'];

    protected $fillable = [
        'integration_account_id',
        'workspace_id',
        'external_id',
        'name',
        'description',
        'timezone',
        'color',
        'access_role',
        'is_primary',
        'is_selected',
        'is_write_target',
        'sync_cursor',
        'last_synced_at',
        'last_error',
    ];

    protected $hidden = [
        'watch_channel_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_selected' => 'boolean',
            'is_write_target' => 'boolean',
            'last_synced_at' => 'datetime',
            'watch_expires_at' => 'datetime',
        ];
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /** Calendars the user has asked Cadence to pull. */
    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('is_selected', true);
    }

    public function isWritable(): bool
    {
        // A calendar with no role recorded is assumed writable: Graph does not
        // report one for a calendar the account owns.
        return $this->access_role === null
            || in_array($this->access_role, self::WRITABLE_ROLES, true);
    }

    /**
     * True when the push channel is missing or close enough to expiry that it
     * should be renewed. Google caps calendar channels at ~30 days and Graph at
     * ~3 days, so renewal is a standing chore rather than a one-off.
     */
    public function needsWatchRenewal(int $graceMinutes = 60): bool
    {
        return $this->watch_expires_at === null
            || $this->watch_expires_at->isBefore(now()->addMinutes($graceMinutes));
    }
}
