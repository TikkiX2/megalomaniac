<?php

use App\Health\Enums\IntakeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_medication_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('health_medications')->cascadeOnDelete();
            $table->timestamp('taken_at');
            $table->enum('status', IntakeStatus::values())->default('taken');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['medication_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_medication_intakes');
    }
};
