<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable();
            $table->string('type', 32)->default('goal'); // vision | goal | objective
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('horizon', 32)->nullable(); // year / quarter / month
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('goals')->nullOnDelete();
            $table->index(['workspace_id', 'user_id']);
            $table->index(['parent_id']);
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->after('project_id')->constrained('goals')->nullOnDelete();
        });

        Schema::create('personal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category', 32)->nullable(); // birthday / anniversary / school / health / other
            $table->date('event_date');
            $table->boolean('recurs_yearly')->default(false);
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'event_date']);
            $table->index(['recurs_yearly']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_events');
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropColumn('goal_id');
        });
        Schema::dropIfExists('goals');
    }
};
