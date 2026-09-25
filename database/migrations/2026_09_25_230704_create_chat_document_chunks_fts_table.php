<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE VIRTUAL TABLE chat_document_chunks_fts USING fts5(
                content,
                content='chat_document_chunks',
                content_rowid='id',
                tokenize='unicode61 remove_diacritics 2'
            );
        ");

        DB::unprepared("
            CREATE TRIGGER chat_document_chunks_ai AFTER INSERT ON chat_document_chunks BEGIN
                INSERT INTO chat_document_chunks_fts(rowid, content) VALUES (new.id, new.content);
            END;
            CREATE TRIGGER chat_document_chunks_ad AFTER DELETE ON chat_document_chunks BEGIN
                INSERT INTO chat_document_chunks_fts(chat_document_chunks_fts, rowid, content) VALUES ('delete', old.id, old.content);
            END;
            CREATE TRIGGER chat_document_chunks_au AFTER UPDATE ON chat_document_chunks BEGIN
                INSERT INTO chat_document_chunks_fts(chat_document_chunks_fts, rowid, content) VALUES ('delete', old.id, old.content);
                INSERT INTO chat_document_chunks_fts(rowid, content) VALUES (new.id, new.content);
            END;
        ");
    }

    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS chat_document_chunks_ai;
            DROP TRIGGER IF EXISTS chat_document_chunks_ad;
            DROP TRIGGER IF EXISTS chat_document_chunks_au;
        ');

        DB::statement('DROP TABLE IF EXISTS chat_document_chunks_fts');
    }
};
