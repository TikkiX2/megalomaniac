<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action_key', 100);
            $table->string('access', 15);
            $table->nullableMorphs('actor');
            $table->string('source', 20);
            $table->json('params');
            $table->text('result_summary')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['connection_id', 'created_at']);
            $table->index(['action_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_action_logs');
    }
};
