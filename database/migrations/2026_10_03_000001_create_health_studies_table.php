<?php

use App\Health\Enums\StudyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('type', StudyType::values());
            $table->string('title');
            $table->date('performed_at')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('health_professionals')->nullOnDelete();
            $table->foreignId('condition_id')->nullable()->constrained('health_conditions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_studies');
    }
};
