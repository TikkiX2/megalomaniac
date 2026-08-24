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
        Schema::create('grocery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable(); // Produce, Dairy, Protein, etc.
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->nullable(); // kg, lbs, count, etc.
            $table->decimal('price', 10, 2)->nullable();
            $table->dateTime('purchased_at')->nullable(); // If null, it's on the shopping list
            $table->boolean('is_purchased')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_items');
    }
};
