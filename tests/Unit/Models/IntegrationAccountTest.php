<?php

namespace Tests\Unit\Models;

use App\Enums\IntegrationProvider;
use App\Models\IntegrationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_is_cast_to_enum(): void
    {
        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        $this->assertSame(IntegrationProvider::Gmail, $account->fresh()->provider);
    }

    public function test_access_token_is_encrypted_at_rest(): void
    {
        $account = IntegrationAccount::factory()->create(['access_token' => 'plain-secret']);

        $raw = DB::table('integration_accounts')->where('id', $account->id)->value('access_token');

        $this->assertNotSame('plain-secret', $raw);
        $this->assertSame('plain-secret', $account->fresh()->access_token);
    }

    public function test_token_is_expired_when_past(): void
    {
        $expired = IntegrationAccount::factory()->expired()->create();
        $valid = IntegrationAccount::factory()->create();

        $this->assertTrue($expired->tokenIsExpired());
        $this->assertFalse($valid->tokenIsExpired());
    }
}
