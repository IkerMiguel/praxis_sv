<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de ejecución (RN-032), suspensiones (RN-031) y verificaciones de calidad (RN-034, RN-035).
     */
    public function up(): void
    {
        Schema::create('actividades_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('plan_intervencion_id')->nullable()->constrained('planes_intervencion')->restrictOnDelete();
            $table->foreignId('tecnico_ejecutor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin')->nullable();
            $table->unsignedInteger('tiempo_invertido_minutos')->nullable();
            $table->text('actividades_realizadas')->nullable();
            $table->text('dificultades_observaciones')->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'fecha_fin']);
        });

        Schema::create('materiales_actividad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_mantenimiento_id')->constrained('actividades_mantenimiento')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->decimal('cantidad', 12, 2);
            $table->string('unidad', 20);
        });

        Schema::create('suspensiones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->string('causal', 60);
            $table->string('motivo_detallado', 1000);
            $table->string('condiciones_reanudacion', 1000);
            $table->string('estado_origen', 3);
            $table->foreignId('usuario_suspende_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_suspension');
            $table->foreignId('usuario_reanuda_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('observacion_reanudacion', 1000)->nullable();
            $table->string('estado_destino', 3)->nullable();
            $table->timestamp('fecha_reanudacion')->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'fecha_reanudacion']);
        });

        Schema::create('verificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('actividad_mantenimiento_id')->nullable()->constrained('actividades_mantenimiento')->restrictOnDelete();
            $table->foreignId('verificador_id')->constrained('users')->restrictOnDelete();
            $table->boolean('resultado_conforme');
            $table->json('criterios_evaluados')->nullable();
            $table->string('observaciones', 1000)->nullable();
            $table->string('motivo_rechazo', 1000)->nullable();
            $table->string('correccion_requerida', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('incidencia_id');
        });

        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->foreign('verificacion_rechazada_id')->references('id')->on('verificaciones')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE verificaciones ADD CONSTRAINT verificaciones_rechazo_check CHECK (resultado_conforme OR (motivo_rechazo IS NOT NULL AND correccion_requerida IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropForeign(['verificacion_rechazada_id']);
        });
        Schema::dropIfExists('verificaciones');
        Schema::dropIfExists('suspensiones');
        Schema::dropIfExists('materiales_actividad');
        Schema::dropIfExists('actividades_mantenimiento');
    }
};
