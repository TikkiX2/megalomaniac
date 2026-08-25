<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detect broken FK: references "food" instead of "foods"
        $isSqlite = DB::getDriverName() === 'sqlite';
        $needsFix = false;

        if ($isSqlite) {
            try {
                $fks = DB::select("PRAGMA foreign_key_list('meal_items')");
                foreach ($fks as $fk) {
                    if ($fk->from === 'food_id' && $fk->table === 'food') {
                        $needsFix = true;
                        break;
                    }
                }
            } catch (Throwable $e) {
                $needsFix = true;
            }
        } else {
            // For mysql/pgsql, just attempt to check information_schema; assume needs fix
            // We can try to see if FK exists with wrong table via Doctrine, but simplest: always attempt fix if table exists
            $needsFix = Schema::hasTable('meal_items');
        }

        if (! $needsFix) {
            return;
        }

        if ($isSqlite) {
            // SQLite: cannot alter FK, need to recreate table
            DB::statement('PRAGMA foreign_keys=OFF');

            // Create new table with correct FK
            Schema::create('meal_items_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meal_log_id')->constrained()->cascadeOnDelete();
                $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
                $table->decimal('quantity', 8, 2)->default(1);
                $table->integer('calories_snapshot');
                $table->decimal('protein_snapshot', 8, 2);
                $table->decimal('carbs_snapshot', 8, 2);
                $table->decimal('fats_snapshot', 8, 2);
                $table->timestamps();
            });

            // Copy data if old table has rows
            $hasData = DB::table('meal_items')->count() > 0;
            if ($hasData) {
                DB::statement('INSERT INTO meal_items_new (id, meal_log_id, food_id, quantity, calories_snapshot, protein_snapshot, carbs_snapshot, fats_snapshot, created_at, updated_at) SELECT id, meal_log_id, food_id, quantity, calories_snapshot, protein_snapshot, carbs_snapshot, fats_snapshot, created_at, updated_at FROM meal_items');
            }

            Schema::drop('meal_items');
            Schema::rename('meal_items_new', 'meal_items');

            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            // MySQL / PG: drop and recreate FK
            try {
                Schema::table('meal_items', function (Blueprint $table) {
                    $table->dropForeign(['food_id']);
                });
            } catch (Throwable $e) {
                // ignore if not exists
            }
            Schema::table('meal_items', function (Blueprint $table) {
                $table->foreign('food_id')->references('id')->on('foods')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // No rollback needed; keep correct FK
    }
};
