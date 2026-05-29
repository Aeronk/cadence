<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\IntegrationAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationAccount extends Model
{
    /** @use HasFactory<IntegrationAccountFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider',
        'external_account_id',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'settings',
        'status',
        'last_error',
        'last_synced_at',
        'sync_cursor',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'scopes' => 'array',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function tokenIsExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
