<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Http\Request;

interface MessagingProvider
{
    /**
     * Send an outbound SMS or WhatsApp message.
     */
    public function send(IntegrationAccount $account, array $payload): Message;

    /**
     * Verify a webhook request's signature.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Parse a verified inbound webhook into a normalized Message-shape array.
     */
    public function parseInbound(Request $request): array;
}
