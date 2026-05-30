<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('priority_id');
            $table->foreignId('milestone_id')->nullable()->after('parent_id')->constrained('milestones')->nullOnDelete();
            $table->index(['workspace_id', 'category']);
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('priority');
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'category']);
            $table->dropForeign(['milestone_id']);
            $table->dropColumn(['category', 'milestone_id']);
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
