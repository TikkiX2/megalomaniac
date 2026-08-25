<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('email')->comment('kg');
            $table->decimal('height', 5, 2)->nullable()->after('weight')->comment('cm');
            $table->decimal('target_weight', 5, 2)->nullable()->after('height')->comment('kg objetivo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weight', 'height', 'target_weight']);
        });
    }
};
