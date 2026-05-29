<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('event_type')->nullable();
            $table->string('external_id')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->unique(['provider', 'external_id'], 'webhook_deliveries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
