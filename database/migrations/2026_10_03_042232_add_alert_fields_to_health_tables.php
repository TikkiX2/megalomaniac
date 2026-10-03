<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            if (! Schema::hasColumn('health_symptoms', 'alerted_at')) {
                $table->timestamp('alerted_at')->nullable();
            }
        });

        Schema::table('health_measurements', function (Blueprint $table) {
            if (! Schema::hasColumn('health_measurements', 'flag')) {
                $table->string('flag')->nullable(); // low, normal, high
            }
            if (! Schema::hasColumn('health_measurements', 'alerted_at')) {
                $table->timestamp('alerted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('health_symptoms', function (Blueprint $table) {
            $table->dropColumn('alerted_at');
        });

        Schema::table('health_measurements', function (Blueprint $table) {
            $table->dropColumn(['flag', 'alerted_at']);
        });
    }
};
