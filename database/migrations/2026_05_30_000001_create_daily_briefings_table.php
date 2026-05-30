<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('briefing_date');
            $table->text('summary');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'workspace_id', 'briefing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_briefings');
    }
};
