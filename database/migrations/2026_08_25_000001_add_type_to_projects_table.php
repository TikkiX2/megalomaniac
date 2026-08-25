<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type')->default('freelance')->after('user_id')->index();
            $table->string('color')->nullable()->after('priority');
            $table->string('icon')->nullable()->after('color');
            $table->decimal('budget', 15, 2)->nullable()->after('estimated_hours');
        });

        // Backfill existing rows as freelance
        \Illuminate\Support\Facades\DB::table('projects')->whereNull('type')->orWhere('type', '')->update(['type' => 'freelance']);
        \Illuminate\Support\Facades\DB::table('projects')->where('type', 'freelance')->update(['type' => 'freelance']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['type', 'color', 'icon', 'budget']);
        });
    }
};
