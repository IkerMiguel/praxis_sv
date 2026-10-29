<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambios de prioridad (RN-020), evaluaciones de riesgo (RN-018) y asignaciones de técnico (RN-021, RN-025).
     */
    public function up(): void
    {
        Schema::create('historial_cambios_prioridad', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('prioridad_anterior_id')->nullable()->constrained('prioridades')->restrictOnDelete();
            $table->foreignId('prioridad_nueva_id')->constrained('prioridades')->restrictOnDelete();
            $table->string('impacto', 5)->nullable();
            $table->string('urgencia', 5)->nullable();
            $table->string('origen', 20);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('justificacion', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('incidencia_id');
        });

        Schema::create('evaluaciones_riesgo', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('evaluador_id')->constrained('users')->restrictOnDelete();
            $table->boolean('tiene_riesgo_inmediato');
            $table->string('nivel_riesgo', 10);
            $table->boolean('protocolo_activado');
            $table->text('acciones_adoptadas')->nullable();
            $table->string('observaciones', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('incidencia_id');
        });

        Schema::create('asignaciones_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('tecnico_asignado_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('asignado_por_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tecnico_anterior_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('es_vigente')->default(true);
            $table->boolean('excepcion_wip')->default(false);
            $table->string('motivo_reasignacion', 500)->nullable();
            $table->string('instrucciones', 1000)->nullable();
            $table->timestamp('fecha_visita_tentativa')->nullable();
            $table->timestamp('fecha_fin_vigencia')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tecnico_asignado_id', 'es_vigente']);
            $table->index('incidencia_id');
        });

        DB::statement('CREATE UNIQUE INDEX asignaciones_incidencia_vigente_unique ON asignaciones_incidencia (incidencia_id) WHERE es_vigente = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_incidencia');
        Schema::dropIfExists('evaluaciones_riesgo');
        Schema::dropIfExists('historial_cambios_prioridad');
    }
};
