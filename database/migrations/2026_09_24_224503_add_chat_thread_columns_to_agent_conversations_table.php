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
            $table->string('space_id', 36)->nullable()->index();
            $table->string('agent', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('mode', 20)->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropColumn(['space_id', 'agent', 'model', 'mode', 'pinned_at', 'archived_at']);
        });
    }
};
