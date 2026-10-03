<?php

use App\Health\Enums\Severity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('health_symptom_catalog')) {
            Schema::create('health_symptom_catalog', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name')->unique();
                $table->enum('severity_default', Severity::values())->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_symptom_catalog');
    }
};
