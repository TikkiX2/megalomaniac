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
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('income_source_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 15, 2);
            $table->date('received_date');
            $table->text('description')->nullable();

            // Recurring income support
            $table->boolean('is_recurring')->default(false);
            $table->integer('recurrence_day')->nullable(); // Día del mes (1-31)
            $table->date('recurrence_end_date')->nullable(); // Fecha fin de recurrencia

            $table->json('metadata')->nullable(); // Para info adicional
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
