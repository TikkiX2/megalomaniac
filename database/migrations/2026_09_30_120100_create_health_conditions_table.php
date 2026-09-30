<?php

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('kind', ConditionKind::values())->default('condition');
            $table->string('name');
            $table->enum('status', ConditionStatus::values())->default('active');
            $table->enum('severity', Severity::values())->nullable();
            $table->date('diagnosed_at')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('health_professionals')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_conditions');
    }
};
