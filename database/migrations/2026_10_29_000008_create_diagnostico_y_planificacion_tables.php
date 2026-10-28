<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diagnósticos de inspección (RN-022..RN-024), solicitudes de recursos (RN-026..RN-030)
     * y planes de intervención (HU-6.3, HU-9.2).
     */
    public function up(): void
    {
        Schema::create('diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('tecnico_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_inicio_inspeccion');
            $table->timestamp('fecha_fin_diagnostico')->nullable();
            $table->boolean('incidencia_confirmada')->nullable();
            $table->text('hallazgos')->nullable();
            $table->text('descripcion_problema')->nullable();
            $table->text('alcance')->nullable();
            $table->text('intervencion_propuesta')->nullable();
            $table->text('justificacion_no_confirmada')->nullable();
            $table->boolean('requiere_recursos')->default(false);
            $table->boolean('requiere_autorizacion')->default(false);
            $table->string('estado', 15)->default('EN_INSPECCION');
            $table->text('observaciones_devolucion')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_revision')->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'estado']);
        });

        Schema::create('solicitudes_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('diagnostico_id')->nullable()->constrained('diagnosticos')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->string('decision_requerida', 500);
            $table->boolean('requiere_autorizacion');
            $table->boolean('es_emergencia')->default(false);
            $table->string('estado_solicitud', 25);
            $table->foreignId('autorizador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resultado_dictamen', 10)->nullable();
            $table->string('justificacion_dictamen', 1000)->nullable();
            $table->boolean('recursos_disponibles')->nullable();
            $table->timestamp('fecha_dictamen')->nullable();
            $table->timestamp('fecha_disponibilidad')->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'estado_solicitud']);
            $table->index('estado_solicitud');
        });

        Schema::create('detalles_solicitud_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_recurso_id')->constrained('solicitudes_recursos')->cascadeOnDelete();
            $table->string('recurso_nombre', 150);
            $table->decimal('cantidad', 12, 2);
            $table->string('unidad', 20);
            $table->decimal('costo_estimado', 14, 2)->default(0);
            $table->boolean('disponible')->default(false);
            $table->timestamps();
        });

        Schema::create('seguimientos_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_recurso_id')->constrained('solicitudes_recursos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('comentario_seguimiento', 1000);
            $table->timestamp('created_at')->useCurrent();
            $table->index('solicitud_recurso_id');
        });

        Schema::create('planes_intervencion', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('diagnostico_id')->nullable()->constrained('diagnosticos')->restrictOnDelete();
            $table->unsignedBigInteger('verificacion_rechazada_id')->nullable();
            $table->foreignId('programado_por_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_programada');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('cuadrilla', 255)->nullable();
            $table->string('estado_plan', 15)->default('VIGENTE');
            $table->string('observaciones', 1000)->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'estado_plan']);
            $table->index('fecha_programada');
        });

        Schema::create('reprogramaciones_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_intervencion_id')->constrained('planes_intervencion')->restrictOnDelete();
            $table->date('fecha_anterior');
            $table->time('hora_inicio_anterior');
            $table->time('hora_fin_anterior');
            $table->date('fecha_nueva');
            $table->time('hora_inicio_nueva');
            $table->time('hora_fin_nueva');
            $table->string('motivo', 500);
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reprogramaciones_plan');
        Schema::dropIfExists('planes_intervencion');
        Schema::dropIfExists('seguimientos_recursos');
        Schema::dropIfExists('detalles_solicitud_recursos');
        Schema::dropIfExists('solicitudes_recursos');
        Schema::dropIfExists('diagnosticos');
    }
};
