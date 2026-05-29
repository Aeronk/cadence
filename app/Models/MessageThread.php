<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MessageThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageThread extends Model
{
    /** @use HasFactory<MessageThreadFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'integration_account_id',
        'channel',
        'external_thread_id',
        'subject',
        'participants',
        'attachable_type',
        'attachable_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'participants' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
