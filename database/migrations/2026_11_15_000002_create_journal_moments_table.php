<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_day_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->text('title')->nullable();
            $table->text('body');
            $table->json('tags')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->index(['journal_day_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_moments');
    }
};
