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
        Schema::create('reserve_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserve_id')->constrained('savings_reserves')->onDelete('cascade');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 15, 2);
            $table->string('transaction_type'); // deposit, withdrawal
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserve_transactions');
    }
};
