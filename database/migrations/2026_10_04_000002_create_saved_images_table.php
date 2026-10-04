<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moodboard_id')->constrained('moodboards')->cascadeOnDelete();
            $table->string('source');
            $table->string('source_id');
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->string('author_url')->nullable();
            $table->text('page_url');
            $table->text('image_url');
            $table->string('thumb_path');
            $table->string('full_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('tags')->nullable();
            $table->string('license')->nullable();
            $table->string('maturity')->nullable();
            $table->text('note')->nullable();
            $table->string('download_status')->default('thumb');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'source', 'source_id']);
            $table->index(['moodboard_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_images');
    }
};
