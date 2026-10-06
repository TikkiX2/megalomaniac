<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspiration_auth', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 50);
            $table->string('type', 10); // cookie | login
            $table->text('data'); // encrypted payload (cookies, or login credentials + cookies)
            $table->boolean('invalid')->default(false);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspiration_auth');
    }
};
