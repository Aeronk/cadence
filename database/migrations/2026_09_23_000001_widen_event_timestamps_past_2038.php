<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move event dates off MySQL's TIMESTAMP type.
 *
 * TIMESTAMP is a 32-bit epoch and stops at 2038-01-19 03:14:07. Calendars
 * routinely contain dates past that: a yearly birthday expands into instances
 * decades out, and Google duly sent one for 2038-05-29, which MySQL rejected
 * with "Incorrect datetime value" and failed the whole sync.
 *
 * DATETIME has no such ceiling, and none of these columns want TIMESTAMP's
 * automatic timezone conversion anyway — the application already normalises to
 * UTC before saving.
 *
 * created_at/updated_at are deliberately left alone: they record when a row was
 * written, which cannot be in 2038.
 */
return new class extends Migration
{
    /**
     * Every column here holds a date somebody or some provider chose, rather
     * than a moment we recorded.
     *
     * @var array<string, array<string, bool>> table => [column => nullable]
     */
    private const COLUMNS = [
        'calendar_events' => ['starts_at' => true, 'ends_at' => true],
        'meetings' => ['starts_at' => false, 'ends_at' => false],
        'trips' => ['departs_at' => false, 'returns_at' => false],
        'trip_segments' => ['starts_at' => false, 'ends_at' => true],
    ];

    public function up(): void
    {
        $this->convert('dateTime');
    }

    public function down(): void
    {
        // Narrowing back will fail on any row already past 2038, which is the
        // point of the change; nothing is silently truncated.
        $this->convert('timestamp');
    }

    private function convert(string $type): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $type) {
                foreach ($columns as $column => $nullable) {
                    $blueprint->{$type}($column)->nullable($nullable)->change();
                }
            });
        }
    }
};
