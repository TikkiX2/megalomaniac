<?php

use App\People\Enums\InteractionChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', InteractionChannel::values());
            $table->timestamp('occurred_at');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_interactions');
    }
};
