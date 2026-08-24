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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->foreignId('category_id')->nullable()->constrained('withdrawal_categories')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('withdrawal_date');
            $table->string('description');
            $table->text('notes')->nullable();

            // Recurring support
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // monthly, weekly, yearly
            $table->integer('recurrence_day')->nullable();
            $table->date('recurrence_end_date')->nullable();

            $table->string('receipt_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
