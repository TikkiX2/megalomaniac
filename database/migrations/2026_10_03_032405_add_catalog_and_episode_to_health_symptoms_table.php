<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            if (! Schema::hasColumn('health_symptoms', 'catalog_id')) {
                $table->foreignId('catalog_id')->nullable()->constrained('health_symptom_catalog')->nullOnDelete();
                $table->index(['user_id', 'catalog_id']);
            }
            if (! Schema::hasColumn('health_symptoms', 'episode_id')) {
                $table->foreignId('episode_id')->nullable()->constrained('health_symptom_episodes')->nullOnDelete();
                $table->index(['user_id', 'episode_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            // SQLite ignore drop foreign constraints on down, but handle columns
            try {
                $table->dropForeign(['catalog_id']);
                $table->dropForeign(['episode_id']);
            } catch (Exception $e) {
            }

            $table->dropColumn(['catalog_id', 'episode_id']);
        });
    }
};
