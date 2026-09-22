<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            // How the goal is actually going, as judged by its owner. Progress
            // answers "how much is done"; status answers "should anyone worry" —
            // a goal can sit at 80% and still be off track.
            $table->string('status', 32)->default('on_track')->after('horizon');
            // Ordering within a parent, so a tree can be arranged by priority
            // rather than alphabetically.
            $table->unsignedInteger('position')->default(0)->after('progress');
        });

        Schema::table('milestones', function (Blueprint $table) {
            // A milestone may now hang off a goal alone. Personal goals rarely
            // have a project behind them, and requiring one forced people to
            // invent throwaway projects just to record a checkpoint.
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['status', 'position']);
        });
    }
};
