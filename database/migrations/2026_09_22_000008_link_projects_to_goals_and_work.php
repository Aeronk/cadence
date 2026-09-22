<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a project the thing other work hangs off, and connects projects to the
 * goals they serve.
 *
 * Until now a project could only reach a goal sideways, through a milestone
 * that happened to carry both ids, and travel, notes and to-dos had no link to
 * project work at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Many-to-many on purpose: one project often serves more than one goal —
        // a platform rebuild can feed both "launch v2" and "cut infrastructure
        // cost" — and forcing a single owner would mean duplicating the project.
        Schema::create('goal_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['goal_id', 'project_id'], 'goal_project_unique');
            $table->index('project_id');
        });

        // Travel is project work: a trip is usually taken *for* something.
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('project_id');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('project_id');
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('project_id');
        });

        // Lets the task page say "this is being done while you are in Nairobi",
        // and lets a trip list the work it exists to get done.
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('meeting_id')->constrained()->nullOnDelete();
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['trip_id']);
            $table->dropConstrainedForeignId('trip_id');
        });

        foreach (['todos', 'notes', 'trips'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['project_id']);
                $blueprint->dropConstrainedForeignId('project_id');
            });
        }

        Schema::dropIfExists('goal_project');
    }
};
