<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per calendar the account can see — Google's CalendarList, or
        // Graph's /me/calendars. Until now Cadence only ever read the calendar
        // literally named "primary", so a shared team calendar or a second
        // personal one was invisible.
        Schema::create('calendar_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('description', 1024)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('color', 16)->nullable();
            // owner | writer | reader | freeBusyReader. Only owner/writer can
            // take a pushed meeting.
            $table->string('access_role', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            // Whether Cadence pulls this calendar at all. Defaults follow the
            // provider's own "selected" flag so a user's hidden calendars stay
            // hidden here too.
            $table->boolean('is_selected')->default(true);
            // Exactly one per account: where meetings Cadence creates are written.
            $table->boolean('is_write_target')->default(false);

            // Sync tokens are per calendar — a token from one calendar is not
            // valid against another, which is why this cannot live on the account.
            $table->text('sync_cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 1024)->nullable();

            // Push channel registration, per calendar.
            $table->string('watch_channel_id')->nullable();
            $table->string('watch_channel_token_hash')->nullable();
            $table->string('watch_resource_id')->nullable();
            $table->timestamp('watch_expires_at')->nullable();

            $table->timestamps();

            $table->unique(['integration_account_id', 'external_id'], 'calendar_sources_unique');
            $table->index(['integration_account_id', 'is_selected']);
            $table->index('watch_expires_at');
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignId('calendar_source_id')
                ->nullable()
                ->after('integration_account_id')
                ->constrained()
                ->cascadeOnDelete();

            // Where the user stands on an invitation, mirrored from the provider
            // so the UI can show it without a second call.
            $table->string('response_status', 32)->nullable()->after('organizer_email');
        });

        // An event id is only unique within a calendar. Google hands the same id
        // to every attendee's copy of an invitation, so syncing two calendars
        // that share an event collided on the old two-column key and the second
        // calendar silently overwrote the first.
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropUnique('calendar_events_unique');
            $table->unique(
                ['integration_account_id', 'calendar_source_id', 'external_id'],
                'calendar_events_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropUnique('calendar_events_unique');
            $table->unique(['integration_account_id', 'external_id'], 'calendar_events_unique');
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calendar_source_id');
            $table->dropColumn('response_status');
        });

        Schema::dropIfExists('calendar_sources');
    }
};
