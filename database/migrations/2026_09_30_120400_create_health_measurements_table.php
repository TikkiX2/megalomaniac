<?php

use App\Health\Enums\MeasurementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('type', MeasurementType::values());
            $table->decimal('value', 8, 2);
            $table->decimal('secondary_value', 8, 2)->nullable();
            $table->string('unit', 20);
            $table->timestamp('measured_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'measured_at']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_measurements');
    }
};
