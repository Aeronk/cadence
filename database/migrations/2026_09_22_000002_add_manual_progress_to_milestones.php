<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            // Null means "derive progress from the milestone's tasks". A value
            // pins it. `progress` stays the cached effective number so existing
            // reads (including Goal::computedProgress) keep working unchanged.
            $table->unsignedTinyInteger('manual_progress')->nullable()->after('progress');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Progress recomputes per milestone; this keeps that lookup cheap.
            $table->index(['milestone_id', 'completed_at'], 'tasks_milestone_completion_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_milestone_completion_index');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('manual_progress');
        });
    }
};
