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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('description')->nullable(); // Rich text JSON
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'maintenance'])->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('deadline')->nullable();

            // Financials
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();

            // Notion-like properties
            $table->string('area')->nullable(); // Frontend, Backend, etc
            $table->string('module')->nullable();
            $table->string('priority')->nullable(); // Q1, Q2, etc
            $table->string('urgency')->nullable(); // Urgente, No urgente
            $table->string('importance')->nullable(); // Importante, No importante
            $table->json('tags')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
