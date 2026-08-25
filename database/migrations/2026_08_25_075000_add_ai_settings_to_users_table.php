<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_provider_url')->nullable()->after('target_weight');
            $table->text('ai_provider_key')->nullable()->after('ai_provider_url');
            $table->string('ai_model', 100)->default('gpt-4')->after('ai_provider_key');
            $table->boolean('ai_enabled')->default(false)->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_provider_url', 'ai_provider_key', 'ai_model', 'ai_enabled']);
        });
    }
};
