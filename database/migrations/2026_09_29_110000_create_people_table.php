<?php

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('nickname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('website')->nullable();
            $table->text('how_we_met')->nullable();
            $table->enum('closeness', Closeness::values())->default('friend');
            $table->enum('relationship_status', RelationshipStatus::values())->nullable();
            $table->enum('preferred_contact_channel', PreferredContactChannel::values())->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'last_contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
