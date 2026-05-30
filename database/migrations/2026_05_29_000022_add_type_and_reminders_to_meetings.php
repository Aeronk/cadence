<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('meeting_type', 32)->default('online')->after('status');
            $table->string('channel', 32)->nullable()->after('meeting_type');
            $table->unsignedSmallInteger('reminder_minutes_before')->nullable()->after('channel');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_minutes_before');

            $table->index(['reminder_sent_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at', 'starts_at']);
            $table->dropColumn(['meeting_type', 'channel', 'reminder_minutes_before', 'reminder_sent_at']);
        });
    }
};
