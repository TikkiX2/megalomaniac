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
        Schema::table('currency_exchanges', function (Blueprint $table) {
            $table->foreignId('to_reserve_id')->nullable()->after('to_currency_id')->constrained('savings_reserves')->nullOnDelete();
            $table->foreignId('reserve_transaction_id')->nullable()->after('income_id')->constrained('reserve_transactions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_exchanges', function (Blueprint $table) {
            $table->dropForeign(['to_reserve_id']);
            $table->dropColumn('to_reserve_id');
            $table->dropForeign(['reserve_transaction_id']);
            $table->dropColumn('reserve_transaction_id');
        });
    }
};
