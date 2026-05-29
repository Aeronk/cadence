<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('external_account_id')->nullable();
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_cursor')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'external_account_id'], 'integration_accounts_unique');
            $table->index(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_accounts');
    }
};
