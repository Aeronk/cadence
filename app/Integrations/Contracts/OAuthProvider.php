<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationAccount;

interface OAuthProvider
{
    /**
     * Build the authorization redirect URL to send the user to.
     */
    public function authorizationUrl(string $state, ?string $redirectUri = null): string;

    /**
     * Exchange an authorization code for tokens. Returns a normalized array:
     * [
     *   'access_token' => string,
     *   'refresh_token' => string|null,
     *   'expires_in' => int|null,
     *   'scope' => string|null,
     *   'external_account_id' => string,
     *   'display_name' => string|null,
     * ]
     */
    public function exchangeCode(string $code, ?string $redirectUri = null): array;

    /**
     * Refresh an expired access token. Returns the same shape as exchangeCode().
     */
    public function refreshAccessToken(IntegrationAccount $account): array;
}
