<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('title', 500);
            $table->string('url', 1000);
            $table->string('author', 150)->nullable();
            $table->text('summary')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');
            $table->json('embedding')->nullable();
            $table->float('score')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->boolean('is_saved')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['feed_source_id', 'external_id']);
            $table->index(['user_id', 'published_at']);
            $table->index(['user_id', 'hidden_at', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
