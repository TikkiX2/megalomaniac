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
            $table->json('tools_policy_backup')->nullable();
        });
    }

    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropColumn('tools_policy_backup');
        });
    }
};
