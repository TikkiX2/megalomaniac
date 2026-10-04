<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moodboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->timestamp('created_at')->nullable();
        });

        // One Inbox (project-less moodboard) per user.
        DB::statement('CREATE UNIQUE INDEX moodboards_user_inbox_unique ON moodboards (user_id) WHERE project_id IS NULL');

        // One moodboard per project and user.
        DB::statement('CREATE UNIQUE INDEX moodboards_user_project_unique ON moodboards (user_id, project_id) WHERE project_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('moodboards');
    }
};
