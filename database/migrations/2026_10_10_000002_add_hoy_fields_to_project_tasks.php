<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->boolean('en_semana')->default(false)->after('is_archived');
            $table->timestamp('archivada_at')->nullable()->after('en_semana');
            $table->index(['user_id', 'en_semana']);
            $table->index(['user_id', 'archivada_at']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'en_semana']);
            $table->dropIndex(['user_id', 'archivada_at']);
            $table->dropColumn(['en_semana', 'archivada_at']);
        });
    }
};
