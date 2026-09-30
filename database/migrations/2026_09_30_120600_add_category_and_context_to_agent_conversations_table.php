<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->string('category', 30)->default('general')->index();
            $table->string('context_type', 100)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['context_type', 'context_id']);
            $table->dropColumn(['category', 'context_type', 'context_id']);
        });
    }
};
