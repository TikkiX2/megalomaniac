<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('key');
            $table->enum('type', ['text', 'number', 'date', 'select', 'multi_select', 'checkbox', 'url', 'person']);
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 2)->nullable();
            $table->date('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['project_task_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_properties');
    }
};
