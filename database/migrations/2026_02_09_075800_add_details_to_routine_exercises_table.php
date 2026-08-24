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
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->integer('target_sets')->nullable()->after('order');
            $table->string('target_reps')->nullable()->after('target_sets');
            $table->string('target_weight')->nullable()->after('target_reps');
            $table->text('notes')->nullable()->after('target_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->dropColumn(['target_sets', 'target_reps', 'target_weight', 'notes']);
        });
    }
};
