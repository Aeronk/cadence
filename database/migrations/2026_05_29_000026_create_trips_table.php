<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('purpose', 64)->nullable(); // donor / fieldwork / personal / conference
            $table->string('destination_country', 2)->nullable();
            $table->string('destination_city')->nullable();
            $table->timestamp('departs_at');
            $table->timestamp('returns_at');
            $table->string('status', 32)->default('planned'); // planned / in_progress / completed / cancelled
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'departs_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('trip_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // flight / train / drive / hotel / other
            $table->string('reference')->nullable(); // flight number, hotel name, etc.
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->longText('details')->nullable(); // confirmation #, address, URL
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'starts_at']);
        });

        Schema::create('trip_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('checked')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_checklist_items');
        Schema::dropIfExists('trip_segments');
        Schema::dropIfExists('trips');
    }
};
