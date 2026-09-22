<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The old two-column key. On MySQL this index is also what satisfies the
     * foreign key on integration_account_id, so it cannot simply be dropped —
     * see swapUniqueIndex().
     */
    private const OLD_INDEX = 'calendar_events_unique';

    private const NEW_INDEX = 'calendar_events_calendar_unique';

    /**
     * Every step is guarded so the migration can be re-run.
     *
     * MySQL does not roll DDL back, so the first release of this migration left
     * databases half-migrated when the index swap failed: the table and columns
     * were committed, but the migration was never recorded. Re-running has to
     * pick up from wherever it stopped rather than failing on "already exists".
     */
    public function up(): void
    {
        // One row per calendar the account can see — Google's CalendarList, or
        // Graph's /me/calendars. Until now Cadence only ever read the calendar
        // literally named "primary", so a shared team calendar or a second
        // personal one was invisible.
        if (! Schema::hasTable('calendar_sources')) {
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
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('calendar_events', 'calendar_source_id')) {
                $table->foreignId('calendar_source_id')
                    ->nullable()
                    ->after('integration_account_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            // Where the user stands on an invitation, mirrored from the provider
            // so the UI can show it without a second call.
            if (! Schema::hasColumn('calendar_events', 'response_status')) {
                $table->string('response_status', 32)->nullable()->after('organizer_email');
            }
        });

        $this->backfillCalendarSources();
        $this->swapUniqueIndex();
    }

    /**
     * Give every existing event a calendar to belong to.
     *
     * Events synced before this migration have no source. The sync now matches
     * on (account, calendar, external_id), so a null-source row would never be
     * matched again and the next sync would insert a second copy of every event
     * — which the calendar page would then draw twice.
     *
     * The placeholder uses the id the old code hard-coded, "primary". For Google
     * that is the real calendar id, so the first calendar-list refresh adopts
     * this row rather than creating a duplicate, and the link from an event back
     * to its Cadence meeting survives intact.
     */
    protected function backfillCalendarSources(): void
    {
        $accountIds = DB::table('calendar_events')
            ->whereNull('calendar_source_id')
            ->distinct()
            ->pluck('integration_account_id');

        foreach ($accountIds as $accountId) {
            $workspaceId = DB::table('integration_accounts')
                ->where('id', $accountId)
                ->value('workspace_id');

            // An event whose account is already gone has nothing to hang off.
            if ($workspaceId === null) {
                continue;
            }

            $sourceId = DB::table('calendar_sources')
                ->where('integration_account_id', $accountId)
                ->where('external_id', 'primary')
                ->value('id');

            $sourceId ??= DB::table('calendar_sources')->insertGetId([
                'integration_account_id' => $accountId,
                'workspace_id' => $workspaceId,
                'external_id' => 'primary',
                'name' => 'Primary',
                'access_role' => 'owner',
                'is_primary' => true,
                'is_selected' => true,
                'is_write_target' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('calendar_events')
                ->where('integration_account_id', $accountId)
                ->whereNull('calendar_source_id')
                ->update(['calendar_source_id' => $sourceId]);
        }
    }

    /**
     * Replace the two-column unique key with one that includes the calendar.
     *
     * An event id is only unique within a calendar. Google hands the same id to
     * every attendee's copy of an invitation, so syncing two calendars that
     * share an event collided on the old key and the second silently overwrote
     * the first.
     *
     * The new index is created before the old one is dropped, and deliberately
     * carries a different name. On MySQL the old index is what satisfies the
     * foreign key on integration_account_id, and dropping it first fails with
     * "Cannot drop index: needed in a foreign key constraint" (errno 1553).
     * Because the new index also begins with integration_account_id, it takes
     * over that duty the moment it exists, and the old one becomes droppable.
     */
    protected function swapUniqueIndex(): void
    {
        $indexes = $this->indexNames();

        if (! in_array(self::NEW_INDEX, $indexes, true)) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->unique(
                    ['integration_account_id', 'calendar_source_id', 'external_id'],
                    self::NEW_INDEX,
                );
            });
        }

        if (in_array(self::OLD_INDEX, $indexes, true)) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }
    }

    /**
     * @return array<int, string>
     */
    protected function indexNames(): array
    {
        return collect(Schema::getIndexes('calendar_events'))
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => strtolower((string) $name))
            ->all();
    }

    public function down(): void
    {
        // Same ordering constraint in reverse: the old index has to exist before
        // the new one can be dropped, or MySQL refuses to leave the foreign key
        // without an index.
        $indexes = $this->indexNames();

        if (! in_array(self::OLD_INDEX, $indexes, true)) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->unique(['integration_account_id', 'external_id'], self::OLD_INDEX);
            });
        }

        if (in_array(self::NEW_INDEX, $indexes, true)) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'calendar_source_id')) {
                $table->dropConstrainedForeignId('calendar_source_id');
            }

            if (Schema::hasColumn('calendar_events', 'response_status')) {
                $table->dropColumn('response_status');
            }
        });

        Schema::dropIfExists('calendar_sources');
    }
};
