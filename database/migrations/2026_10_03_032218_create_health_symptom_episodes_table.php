<?php

use App\Health\Enums\Severity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('health_symptom_episodes')) {
            Schema::create('health_symptom_episodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->foreignId('catalog_id')->nullable()->constrained('health_symptom_catalog')->nullOnDelete();
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->enum('severity', Severity::values())->default('mild');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'started_at']);
                $table->index(['user_id', 'catalog_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_symptom_episodes');
    }
};
