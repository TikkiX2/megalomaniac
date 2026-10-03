<?php

use App\Health\Enums\ResultFlag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_study_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained('health_studies')->cascadeOnDelete();
            $table->string('analyte');
            $table->string('value');
            $table->string('unit')->nullable();
            $table->string('reference_range')->nullable();
            $table->enum('flag', ResultFlag::values())->nullable();
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('study_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_study_results');
    }
};
