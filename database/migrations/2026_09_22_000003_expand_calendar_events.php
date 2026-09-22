<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->boolean('all_day')->default(false)->after('location');
            // RRULE lines exactly as the provider returns them.
            $table->json('recurrence')->nullable()->after('all_day');
            // Set on expanded instances; points at the series master's external id.
            $table->string('recurring_event_id')->nullable()->after('recurrence');
            $table->string('organizer_email')->nullable()->after('recurring_event_id');
            $table->string('html_link', 1024)->nullable()->after('organizer_email');
            $table->string('conference_url', 1024)->nullable()->after('html_link');

            // All-day events carry a date with no time, and a provider can return
            // an event with neither; the reading code already assumed nullable.
            $table->timestamp('starts_at')->nullable()->change();
            $table->timestamp('ends_at')->nullable()->change();

            $table->index('recurring_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['recurring_event_id']);
            $table->dropColumn([
                'all_day', 'recurrence', 'recurring_event_id',
                'organizer_email', 'html_link', 'conference_url',
            ]);
        });
    }
};
