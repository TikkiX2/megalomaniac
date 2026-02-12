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
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('from_amount', 18, 2);
            $table->decimal('to_amount', 18, 2);
            $table->decimal('exchange_rate', 24, 8);
            $table->date('exchange_date');
            $table->text('notes')->nullable();

            // Link to the actual ledger entries
            $table->foreignId('withdrawal_id')->nullable()->constrained('withdrawals')->nullOnDelete();
            $table->foreignId('income_id')->nullable()->constrained('incomes')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
