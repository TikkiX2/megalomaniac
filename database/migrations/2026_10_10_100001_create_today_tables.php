<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('day_items');
        Schema::dropIfExists('days');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('queue_items');

        // Limpieza de tablas del módulo Hoy (nombres ES) si quedaron de una versión previa.
        Schema::dropIfExists('dia_items');
        Schema::dropIfExists('dias');
        Schema::dropIfExists('bloques');
        Schema::dropIfExists('cola_media');

        Schema::create('days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('pick_type')->default('pelicula');
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('day_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('day_id')->constrained('days')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('title');
            $table->string('anchor')->default('no_anchor');
            $table->unsignedTinyInteger('position');
            $table->string('state')->default('pending');
            $table->string('closing_note')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX day_items_day_position_visible ON day_items (day_id, position) WHERE state != 'released'");

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->integer('duration_min');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->integer('position')->default(0);
            $table->string('source')->nullable();
            $table->string('external_id')->nullable();
            $table->string('cover_url')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('creator')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'position']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_items');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('day_items');
        Schema::dropIfExists('days');
        // Note: The partial unique index is tied to the table; dropping the table removes it.
    }
};
