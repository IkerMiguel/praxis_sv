<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos que gobiernan el proceso: estados (D07), categorías (HU-11.2), prioridades con SLA,
     * matriz ITIL (HU-11.3), parámetros del sistema, feriados y permisos por módulo (D09).
     */
    public function up(): void
    {
        Schema::create('estados_incidencia', function (Blueprint $table) {
            $table->string('codigo', 3)->primary();
            $table->string('nombre', 80);
            $table->string('categoria', 20);
            $table->string('significado_publico', 500);
            $table->boolean('requiere_accion_reportante')->default(false);
            $table->boolean('es_terminal')->default(false);
            $table->string('color', 20);
            $table->unsignedTinyInteger('orden');
        });

        Schema::create('categorias_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_padre_id')->nullable()->constrained('categorias_incidencia')->restrictOnDelete();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('exige_evidencia_fotografica')->default(false);
            $table->boolean('requiere_confirmacion_cierre')->default(true);
            $table->string('nivel_riesgo_base', 10)->default('BAJO');
            $table->foreignId('especialidad_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['categoria_padre_id', 'activo']);
        });

        Schema::create('prioridades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 40);
            $table->unsignedTinyInteger('nivel')->unique();
            $table->unsignedSmallInteger('horas_primera_respuesta');
            $table->unsignedSmallInteger('horas_resolucion');
            $table->string('color', 20);
            $table->timestamps();
        });

        Schema::create('matriz_prioridad', function (Blueprint $table) {
            $table->id();
            $table->string('impacto', 5);
            $table->string('urgencia', 5);
            $table->foreignId('prioridad_id')->constrained('prioridades')->restrictOnDelete();
            $table->foreignId('modificado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['impacto', 'urgencia']);
        });

        Schema::create('parametros_sistema', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255);
            $table->string('tipo_dato', 15);
            $table->string('grupo', 30);
            $table->string('descripcion', 255);
            $table->foreignId('modificado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->date('fecha')->primary();
            $table->string('descripcion', 120);
        });

        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('etiqueta', 80);
            $table->string('ruta', 120);
            $table->string('icono', 40)->nullable();
            $table->string('grupo', 40)->nullable();
            $table->unsignedSmallInteger('orden');
            $table->boolean('en_menu')->default(true);
        });

        Schema::create('rol_modulo_permisos', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->string('nivel_acceso', 15);
            $table->primary(['rol_id', 'modulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_modulo_permisos');
        Schema::dropIfExists('modulos');
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('parametros_sistema');
        Schema::dropIfExists('matriz_prioridad');
        Schema::dropIfExists('prioridades');
        Schema::dropIfExists('categorias_incidencia');
        Schema::dropIfExists('estados_incidencia');
    }
};
