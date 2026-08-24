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
        Schema::table('grocery_items', function (Blueprint $table) {
            $table->decimal('current_stock', 8, 2)->default(0);
            $table->decimal('target_stock', 8, 2)->default(1);
            $table->dropColumn('is_purchased');
            // purchased_at remains to track last restock date
        });

        Schema::create('grocery_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('quantity', 8, 2); // Amount bought
            $table->timestamp('purchased_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_price_history');

        Schema::table('grocery_items', function (Blueprint $table) {
            $table->dropColumn(['current_stock', 'target_stock']);
            $table->boolean('is_purchased')->default(false);
        });
    }
};
