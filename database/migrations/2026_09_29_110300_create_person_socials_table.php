<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('network', 50);
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_socials');
    }
};
