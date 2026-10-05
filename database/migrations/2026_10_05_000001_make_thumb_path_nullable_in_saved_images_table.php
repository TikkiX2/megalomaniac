<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review Focus 2: a failed thumbnail download leaves `thumb_path` null so
     * the frontend falls back to the remote `image_url`. The Task 1 migration
     * declared the column NOT NULL, so this corrective migration follows the
     * repo convention (see the nullable `projects` columns migration).
     */
    public function up(): void
    {
        Schema::table('saved_images', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('saved_images', function (Blueprint $table) {
            $table->string('thumb_path')->nullable(false)->change();
        });
    }
};
