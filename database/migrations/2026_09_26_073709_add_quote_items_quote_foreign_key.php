<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres refuses to create the quote_items -> quotes foreign key while
     * "quotes" does not exist yet (both migrations share the same timestamp
     * and quote_items runs first). SQLite tolerated it, so existing databases
     * already have the constraint and this migration is a no-op there.
     */
    public function up(): void
    {
        if (! Schema::hasTable('quote_items') || ! Schema::hasTable('quotes')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Keep the constraint on rollback; it is part of the expected schema.
    }

    private function foreignKeyExists(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA foreign_key_list('quote_items')") as $foreignKey) {
                if ($foreignKey->from === 'quote_id' && $foreignKey->table === 'quotes') {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function ($join) {
                $join->on('kcu.constraint_name', '=', 'tc.constraint_name')
                    ->on('kcu.table_schema', '=', 'tc.table_schema');
            })
            ->where('tc.table_schema', 'public')
            ->where('tc.table_name', 'quote_items')
            ->where('tc.constraint_type', 'FOREIGN KEY')
            ->where('kcu.column_name', 'quote_id')
            ->exists();
    }
};
