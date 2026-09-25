<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->timestamps();

            $table->unique(['feed_item_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_signals');
    }
};
