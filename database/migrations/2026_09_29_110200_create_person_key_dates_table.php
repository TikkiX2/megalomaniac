<?php

use App\People\Enums\KeyDateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_key_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->enum('type', KeyDateType::values())->default('custom');
            $table->string('label')->nullable();
            $table->date('date');
            $table->unsignedTinyInteger('remind_days_before')->default(7);
            $table->boolean('is_recurring_annually')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_key_dates');
    }
};
