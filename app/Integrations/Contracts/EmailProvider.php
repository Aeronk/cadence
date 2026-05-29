<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationAccount;
use App\Models\Message;

interface EmailProvider
{
    /**
     * Pull new messages since the account's stored cursor.
     * Returns the number of messages persisted.
     */
    public function syncInbox(IntegrationAccount $account): int;

    /**
     * Send an outbound email. Should persist the Message and return it.
     */
    public function send(IntegrationAccount $account, array $payload): Message;

    /**
     * Subscribe to inbox push notifications (Gmail watch / Graph subscription).
     */
    public function watchInbox(IntegrationAccount $account): void;
}
