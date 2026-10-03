<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            $table->foreignId('catalog_id')->nullable()->constrained('health_symptom_catalog');
            $table->foreignId('episode_id')->nullable()->constrained('health_symptom_episodes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            $table->dropForeign(['catalog_id']);
            $table->dropForeign(['episode_id']);
            $table->dropColumn(['catalog_id', 'episode_id']);
        });
    }
};
