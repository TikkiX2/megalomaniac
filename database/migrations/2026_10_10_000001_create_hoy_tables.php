<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->timestamps();
            $table->unique(['user_id', 'fecha']);
        });

        Schema::create('dia_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dia_id')->constrained('dias')->cascadeOnDelete();
            $table->foreignId('tarea_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('titulo');
            $table->string('ancla')->default('sin ancla');
            $table->unsignedTinyInteger('posicion');
            $table->string('estado')->default('pendiente');
            $table->string('nota_cierre')->nullable();
            $table->timestamp('hecho_at')->nullable();
            $table->timestamps();
            // UNIQUE parcial: solo visibles ocupan slot; soltado libera.
            // Se crea abajo por compatibilidad sqlite/pgsql.
        });

        Schema::create('bloques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('etiqueta');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->integer('duracion_min');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cola_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo');
            $table->string('tipo');
            $table->integer('posicion')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'posicion']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX dia_items_dia_posicion_visibles ON dia_items (dia_id, posicion) WHERE estado != 'soltado'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cola_media');
        Schema::dropIfExists('bloques');
        Schema::dropIfExists('dia_items');
        Schema::dropIfExists('dias');
    }
};
