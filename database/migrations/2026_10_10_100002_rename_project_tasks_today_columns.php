<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Base histórica: las columnas existen con nombres ES (migración previa ya aplicada en algún entorno).
        if (Schema::hasColumn('project_tasks', 'en_semana') && ! Schema::hasColumn('project_tasks', 'in_week')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'en_semana']);
                $table->dropIndex(['user_id', 'archivada_at']);
            });

            Schema::table('project_tasks', function (Blueprint $table) {
                $table->renameColumn('en_semana', 'in_week');
                $table->renameColumn('archivada_at', 'archived_at');
            });
        }

        // Fresh (o entorno sin la migración vieja): crear directo con nombres EN.
        if (! Schema::hasColumn('project_tasks', 'in_week')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->boolean('in_week')->default(false);
                $table->timestamp('archived_at')->nullable();
            });
        }

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->index(['user_id', 'in_week']);
            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'in_week']);
            $table->dropIndex(['user_id', 'archived_at']);
        });

        if (Schema::hasColumn('project_tasks', 'in_week')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->renameColumn('in_week', 'en_semana');
                $table->renameColumn('archived_at', 'archivada_at');
            });
        }
    }
};
