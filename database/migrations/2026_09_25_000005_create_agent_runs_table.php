<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 15);
            $table->string('triggered_by', 15);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('report')->nullable();
            $table->unsignedSmallInteger('suggestions_created')->default(0);
            $table->unsignedSmallInteger('approvals_created')->default(0);
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['agent_definition_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
