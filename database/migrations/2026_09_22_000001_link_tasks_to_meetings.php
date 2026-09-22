<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('milestone_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('meetings', function (Blueprint $table) {
            // Set when the event should carry an RRULE instead of one event per
            // occurrence. Mirrors tasks.recurrence_rule (daily|weekly|monthly|yearly).
            $table->string('recurrence_rule', 32)->nullable()->after('reminder_sent_at');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_rule');
            // Ask the provider to mint a conference link (Google Meet) on push.
            $table->boolean('conference_requested')->default(false)->after('recurrence_ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['recurrence_rule', 'recurrence_ends_on', 'conference_requested']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['meeting_id']);
            $table->dropColumn('meeting_id');
        });
    }
};
