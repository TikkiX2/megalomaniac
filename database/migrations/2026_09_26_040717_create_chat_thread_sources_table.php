<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_thread_sources', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id', 36);
            $table->string('attachment_id', 36);
            $table->timestamps();

            $table->unique(['thread_id', 'attachment_id']);
            $table->index('attachment_id');

            $table->foreign('thread_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
            $table->foreign('attachment_id')->references('id')->on('chat_attachments')->cascadeOnDelete();
        });

        $this->backfill();
    }

    /**
     * Copy the legacy `chat_attachments.thread_id` documents into the pivot.
     * Kept public so the migration test can exercise the exact statement.
     */
    public function backfill(): void
    {
        DB::statement(
            "INSERT INTO chat_thread_sources (thread_id, attachment_id, created_at, updated_at)
             SELECT thread_id, id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM chat_attachments
             WHERE kind = 'document' AND thread_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_thread_sources');
    }
};
