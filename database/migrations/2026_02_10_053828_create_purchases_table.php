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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->foreignId('category_id')->nullable()->constrained('purchase_categories')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('purchase_date');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable(); // Para guardar foto del recibo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
