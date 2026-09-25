<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->text('instructions');
            $table->json('tools_policy');
            $table->string('schedule_type', 10)->default('interval');
            $table->string('schedule_value', 50)->default('1h');
            $table->string('timezone', 50)->default('UTC');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('max_runs_per_day')->default(24);
            $table->unsignedInteger('max_tokens_per_run')->default(2000);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_definitions');
    }
};
