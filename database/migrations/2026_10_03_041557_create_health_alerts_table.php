<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('health_alerts')) {
            Schema::create('health_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // 'symptom_severe', 'study_result_high'
                $table->foreignId('related_id')->nullable(); // symptom_id, study_result_id
                $table->string('related_type')->nullable(); // HealthSymptom, HealthStudyResult
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamp('triggered_at')->useCurrent();
                $table->timestamps();

                $table->index(['user_id', 'is_read', 'triggered_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_alerts');
    }
};
