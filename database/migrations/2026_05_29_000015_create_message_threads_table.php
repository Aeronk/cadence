<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('external_thread_id')->nullable();
            $table->string('subject')->nullable();
            $table->json('participants')->nullable();
            $table->nullableMorphs('attachable');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'channel']);
            $table->index(['channel', 'external_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
