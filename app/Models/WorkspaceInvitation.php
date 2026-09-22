<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkspaceInvitation extends Model
{
    /** How long an invitation link stays usable. */
    public const TTL_DAYS = 7;

    /** Minimum gap between resends of the same invitation. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $fillable = [
        'workspace_id',
        'invited_by_id',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'accepted_by_id',
        'last_sent_at',
        'send_count',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }

    /**
     * The plaintext token, available only on the instance that just minted it.
     * It is never persisted, so it cannot be recovered from a later read.
     */
    protected ?string $plainToken = null;

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function setPlainToken(string $token): void
    {
        $this->plainToken = $token;
        $this->token_hash = static::hashToken($token);
    }

    public function plainToken(): ?string
    {
        return $this->plainToken;
    }

    /**
     * Mint a fresh token, invalidating any link already in someone's inbox.
     * Used on resend so a forwarded old mail stops working.
     */
    public function rotateToken(): string
    {
        $token = static::generateToken();
        $this->setPlainToken($token);

        return $token;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Whether enough time has passed to send this invitation again. Guards the
     * resend button against being used to mail-bomb an address.
     */
    public function canBeResent(): bool
    {
        if ($this->isAccepted()) {
            return false;
        }

        return $this->last_sent_at === null
            || $this->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isPast();
    }

    public function secondsUntilResendAllowed(): int
    {
        if ($this->last_sent_at === null) {
            return 0;
        }

        return max(0, now()->diffInSeconds(
            $this->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            false,
        ));
    }

    public function markSent(): void
    {
        $this->forceFill([
            'last_sent_at' => now(),
            'send_count' => $this->send_count + 1,
        ])->save();
    }

    public function acceptUrl(): string
    {
        return route('workspace.invitations.accept', ['token' => $this->plainToken]);
    }

    /** @param  Builder<WorkspaceInvitation>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
