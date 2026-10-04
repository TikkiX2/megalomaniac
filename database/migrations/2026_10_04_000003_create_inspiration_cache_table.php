<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspiration_cache', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64);
            $table->string('kind', 16);
            $table->string('query_hash', 64);
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');

            $table->unique(['source', 'kind', 'query_hash']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspiration_cache');
    }
};
