<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('description');
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->boolean('dismissed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'dismissed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_suggestions');
    }
};
