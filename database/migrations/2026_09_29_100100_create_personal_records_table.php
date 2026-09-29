<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_set_id')->nullable()->constrained('workout_sets')->nullOnDelete();
            $table->string('type');
            $table->decimal('value', 8, 2);
            $table->integer('reps')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->dateTime('achieved_at');
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'achieved_at'], 'personal_records_user_exercise_achieved_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
