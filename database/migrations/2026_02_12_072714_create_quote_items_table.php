<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();

            // Postgres requires the referenced table to exist, and "quotes" is
            // created right after this migration (same timestamp). On pgsql the
            // constraint is added by 2026_09_26_073709_add_quote_items_quote_foreign_key.
            $quoteId = $table->foreignId('quote_id');
            if (DB::getDriverName() !== 'pgsql') {
                $quoteId->constrained()->cascadeOnDelete();
            }

            $table->text('description');

            $table->decimal('hours', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable(); // Can override quote base rate

            $table->decimal('subtotal', 15, 2)->default(0); // hours * rate

            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
