<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 100);
            $table->json('config');
            $table->boolean('enabled')->default(true);
            $table->foreignId('connection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('fetch_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'name']);
            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
    }
};
