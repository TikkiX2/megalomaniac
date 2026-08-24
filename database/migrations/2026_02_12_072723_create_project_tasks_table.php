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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('description')->nullable();
            $table->string('status')->default('Pending'); // Flexible string for Notion sync
            $table->string('responsible')->nullable();
            $table->string('urgency')->nullable(); // Urgente, No urgente
            $table->string('importance')->nullable(); // Importante, No importante
            $table->string('priority')->nullable(); // Q1, Q2, etc
            $table->string('module')->nullable();
            $table->json('tags')->nullable();
            $table->string('area')->nullable();
            $table->date('due_date')->nullable();

            // Notion Sync
            $table->string('notion_page_id')->nullable()->index();
            $table->timestamp('notion_last_sync')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
