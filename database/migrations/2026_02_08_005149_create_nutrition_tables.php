<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->integer('calories');
            $table->decimal('protein', 8, 2);
            $table->decimal('carbs', 8, 2);
            $table->decimal('fats', 8, 2);
            $table->decimal('serving_size', 8, 2)->nullable();
            $table->string('serving_unit')->nullable(); // e.g., 'g', 'ml', 'cup'
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_type'); // breakfast, lunch, dinner, snack
            $table->timestamps();
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->decimal('quantity', 8, 2)->default(1); // Multiplier of serving size
            // Snapshots to preserve history if food changes
            $table->integer('calories_snapshot');
            $table->decimal('protein_snapshot', 8, 2);
            $table->decimal('carbs_snapshot', 8, 2);
            $table->decimal('fats_snapshot', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('meal_logs');
        Schema::dropIfExists('foods');
    }
};
