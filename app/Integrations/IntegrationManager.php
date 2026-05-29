<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\Contracts\CalendarProvider;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\MessagingProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Models\IntegrationAccount;
use InvalidArgumentException;
use RuntimeException;

class IntegrationManager
{
    /**
     * @var array<string, class-string>
     */
    protected array $bindings = [];

    public function bind(IntegrationProvider $provider, string $implementation): void
    {
        $this->bindings[$provider->value] = $implementation;
    }

    public function oauth(IntegrationProvider $provider): OAuthProvider
    {
        $instance = $this->resolve($provider);

        if (! $instance instanceof OAuthProvider) {
            throw new RuntimeException("Provider {$provider->value} does not implement OAuthProvider.");
        }

        return $instance;
    }

    public function email(IntegrationAccount $account): EmailProvider
    {
        $instance = $this->resolve($account->provider);

        if (! $instance instanceof EmailProvider) {
            throw new RuntimeException("Account provider {$account->provider->value} does not implement EmailProvider.");
        }

        return $instance;
    }

    public function calendar(IntegrationAccount $account): CalendarProvider
    {
        $instance = $this->resolve($account->provider);

        if (! $instance instanceof CalendarProvider) {
            throw new RuntimeException("Account provider {$account->provider->value} does not implement CalendarProvider.");
        }

        return $instance;
    }

    public function messaging(IntegrationAccount $account): MessagingProvider
    {
        $instance = $this->resolve($account->provider);

        if (! $instance instanceof MessagingProvider) {
            throw new RuntimeException("Account provider {$account->provider->value} does not implement MessagingProvider.");
        }

        return $instance;
    }

    protected function resolve(IntegrationProvider $provider): object
    {
        $class = $this->bindings[$provider->value]
            ?? throw new InvalidArgumentException("No implementation bound for {$provider->value}.");

        return app($class);
    }
}
