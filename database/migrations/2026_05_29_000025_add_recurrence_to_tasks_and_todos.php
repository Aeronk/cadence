<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_rule', 32)->nullable()->after('category');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_rule');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_ends_on');
            $table->index('recurrence_parent_id');
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->string('recurrence_rule', 32)->nullable()->after('category');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_rule');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_ends_on');
            $table->index('recurrence_parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['recurrence_parent_id']);
            $table->dropColumn(['recurrence_rule', 'recurrence_ends_on', 'recurrence_parent_id']);
        });
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['recurrence_parent_id']);
            $table->dropColumn(['recurrence_rule', 'recurrence_ends_on', 'recurrence_parent_id']);
        });
    }
};
