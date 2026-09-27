<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->uuid('thread_id')->nullable();
            $table->text('content');
            $table->string('source', 16)->default('agent');
            $table->char('content_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
            $table->index(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
