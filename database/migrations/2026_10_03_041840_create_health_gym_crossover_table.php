<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('health_gym_crossovers')) {
            Schema::create('health_gym_crossovers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('symptom_id')->constrained('health_symptoms')->onDelete('cascade');
                $table->foreignId('workout_set_id')->constrained('workout_sets')->onDelete('cascade');
                $table->dateTime('occurred_at');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'symptom_id']);
                $table->index(['user_id', 'workout_set_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_gym_crossovers');
    }
};
