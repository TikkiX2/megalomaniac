<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('thread_id', 36)->nullable()->index();
            $table->string('message_id', 36)->nullable()->index();
            $table->string('kind', 10);
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('status', 12)->default('ready');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'thread_id']);
        });

        Schema::create('chat_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('attachment_id', 36)->index();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->timestamps();

            $table->foreign('attachment_id')->references('id')->on('chat_attachments')->cascadeOnDelete();
            $table->index(['attachment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_document_chunks');
        Schema::dropIfExists('chat_attachments');
    }
};
