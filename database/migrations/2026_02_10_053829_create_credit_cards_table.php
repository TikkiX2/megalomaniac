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
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // "Tarjeta de María", "Visa Personal", etc.
            $table->string('last_four_digits', 4)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users'); // Si no es tuya
            $table->boolean('is_mine')->default(true);

            // Interest and tax configuration
            $table->decimal('interest_rate', 5, 2)->default(0); // Porcentaje de interés mensual
            $table->decimal('tax_percentage', 5, 2)->default(0); // Porcentaje de impuestos
            $table->boolean('apply_interest')->default(false);
            $table->boolean('apply_tax')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
