<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('budget', 14, 2)->nullable()->after('due_date');
            $table->string('budget_currency', 3)->nullable()->after('budget');
            $table->string('state', 16)->default('active')->after('budget_currency');
            $table->timestamp('completed_at')->nullable()->after('state');
            $table->timestamp('on_hold_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget', 'budget_currency', 'state', 'completed_at', 'on_hold_at']);
        });
    }
};
