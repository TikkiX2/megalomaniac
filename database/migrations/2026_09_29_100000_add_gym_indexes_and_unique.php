<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('workout_sets')
            ->select('workout_exercise_id', 'set_number', DB::raw('MAX(id) as keep_id'))
            ->groupBy('workout_exercise_id', 'set_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('workout_sets')
                ->where('workout_exercise_id', $duplicate->workout_exercise_id)
                ->where('set_number', $duplicate->set_number)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('workout_sets', function (Blueprint $table) {
            $table->unique(['workout_exercise_id', 'set_number'], 'workout_sets_exercise_set_unique');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->index(['user_id', 'started_at'], 'workouts_user_started_index');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->index(['workout_id', 'order'], 'workout_exercises_workout_order_index');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->index('name', 'exercises_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropUnique('workout_sets_exercise_set_unique');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex('workouts_user_started_index');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropIndex('workout_exercises_workout_order_index');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex('exercises_name_index');
        });
    }
};
