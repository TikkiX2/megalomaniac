<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('label', 80);
            $table->string('color', 20)->default('slate');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_board_columns');
    }
};
