<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->date('start_date')->nullable()->after('due_date');
            $table->integer('estimated_time')->nullable()->after('start_date');
            $table->integer('actual_time')->nullable()->after('estimated_time');
            $table->integer('sort_order')->default(0)->after('actual_time');
            $table->boolean('is_archived')->default(false)->after('sort_order');
        });

        \Illuminate\Support\Facades\DB::statement('UPDATE project_tasks SET user_id = (SELECT user_id FROM projects WHERE projects.id = project_tasks.project_id) WHERE user_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['start_date', 'estimated_time', 'actual_time', 'sort_order', 'is_archived']);
        });
    }
};
