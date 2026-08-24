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
        Schema::create('supplements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('dosage_amount')->nullable(); // e.g. '5g', '1 tablet'
            $table->string('frequency')->nullable(); // e.g. 'Daily', 'Post-Workout'
            $table->integer('stock_quantity')->default(0); // in servings or units
            $table->integer('low_stock_threshold')->default(5);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('supplement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplement_id')->constrained()->cascadeOnDelete();
            $table->dateTime('taken_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplement_logs');
        Schema::dropIfExists('supplements');
    }
};
