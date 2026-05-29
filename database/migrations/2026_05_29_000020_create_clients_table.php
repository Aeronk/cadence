<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('website')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'name']);
        });

        Schema::create('client_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project');
        Schema::dropIfExists('clients');
    }
};
