<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_moment_person', function (Blueprint $table) {
            $table->foreignId('moment_id')->constrained('journal_moments')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['moment_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_moment_person');
    }
};
