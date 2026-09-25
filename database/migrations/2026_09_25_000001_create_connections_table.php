<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->string('name', 100);
            $table->string('auth_type', 30)->default('api_token');
            $table->text('credentials')->nullable();
            $table->string('base_url', 500)->nullable();
            $table->string('transport', 20)->default('direct');
            $table->text('transport_config')->nullable();
            $table->json('options')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 20)->default('unknown');
            $table->text('status_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
