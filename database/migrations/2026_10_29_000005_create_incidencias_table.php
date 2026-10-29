<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entidad central (D14). Identificador UUID y código de ticket único TKT-AAAA-###### (RN-001).
     */
    public function up(): void
    {
        Schema::create('consecutivos_ticket', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('incidencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_ticket', 15)->unique();
            $table->string('resumen', 150);
            $table->text('descripcion');
            $table->string('canal_radicacion', 20)->default('WEB');

            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('edificio_id')->constrained('edificios')->restrictOnDelete();
            $table->foreignId('piso_id')->constrained('pisos')->restrictOnDelete();
            $table->foreignId('espacio_fisico_id')->constrained('espacios_fisicos')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias_incidencia')->restrictOnDelete();
            $table->foreignId('subcategoria_id')->nullable()->constrained('categorias_incidencia')->restrictOnDelete();

            $table->string('estado_codigo', 3);
            $table->foreign('estado_codigo')->references('codigo')->on('estados_incidencia')->restrictOnUpdate()->restrictOnDelete();
            $table->string('impacto', 5)->nullable();
            $table->string('urgencia', 5)->nullable();
            $table->foreignId('prioridad_id')->nullable()->constrained('prioridades')->restrictOnDelete();

            $table->foreignId('usuario_creador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tecnico_asignado_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('clave_idempotencia', 100)->nullable();

            $table->boolean('es_riesgo_inmediato')->default(false);
            $table->string('descripcion_riesgo', 500)->nullable();
            $table->boolean('protocolo_emergencia_activo')->default(false);
            $table->boolean('posible_duplicado')->default(false);
            $table->uuid('incidencia_principal_id')->nullable();

            $table->string('motivo_cancelacion', 500)->nullable();
            $table->foreignId('usuario_cancelacion_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('numero_reaperturas')->default(0);
            $table->timestamp('fecha_ultima_reapertura')->nullable();
            $table->unsignedTinyInteger('numero_reprocesos')->default(0);

            $table->unsignedTinyInteger('calificacion_servicio')->nullable();
            $table->string('comentario_conformidad', 500)->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->boolean('es_cierre_administrativo')->default(false);
            $table->string('resultado_final_cierre', 60)->nullable();
            $table->string('notas_cierre', 1000)->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->foreignId('usuario_cierre_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamp('fecha_primera_respuesta')->nullable();
            $table->timestamp('fecha_limite_respuesta')->nullable();
            $table->timestamp('fecha_limite_resolucion')->nullable();
            $table->boolean('sla_respuesta_cumplido')->nullable();
            $table->boolean('sla_resolucion_cumplido')->nullable();

            $table->timestamp('fecha_ultimo_cambio_estado')->nullable();
            $table->timestamps();

            $table->unique(['usuario_creador_id', 'clave_idempotencia']);
            $table->index(['usuario_creador_id', 'estado_codigo']);
            $table->index(['usuario_creador_id', 'created_at']);
            $table->index(['espacio_fisico_id', 'categoria_id', 'estado_codigo', 'created_at'], 'incidencias_coincidencias_index');
            $table->index(['estado_codigo', 'prioridad_id', 'created_at'], 'incidencias_consola_index');
            $table->index(['sede_id', 'categoria_id']);
            $table->index(['tecnico_asignado_id', 'estado_codigo']);
            $table->index(['fecha_cierre', 'created_at']);
            $table->index('incidencia_principal_id');
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->foreign('incidencia_principal_id')->references('id')->on('incidencias')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE incidencias ADD CONSTRAINT incidencias_codigo_ticket_formato_check CHECK (codigo_ticket ~ '^TKT-[0-9]{4}-[0-9]{6}$')");
            DB::statement('ALTER TABLE incidencias ADD CONSTRAINT incidencias_calificacion_check CHECK (calificacion_servicio IS NULL OR calificacion_servicio BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE incidencias ADD CONSTRAINT incidencias_no_auto_duplicado_check CHECK (incidencia_principal_id IS NULL OR incidencia_principal_id <> id)');
            DB::statement('CREATE INDEX incidencias_busqueda_texto_index ON incidencias USING gin (to_tsvector(\'spanish\', resumen))');
        }

        Schema::create('seguidores_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('fecha_suscripcion')->useCurrent();
            $table->unique(['incidencia_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('duplicados_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_principal_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignUuid('incidencia_duplicada_id')->unique()->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('usuario_confirmacion_id')->constrained('users')->restrictOnDelete();
            $table->string('observaciones', 500)->nullable();
            $table->timestamp('fecha_vinculacion')->useCurrent();
            $table->index('incidencia_principal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicados_incidencia');
        Schema::dropIfExists('seguidores_incidencia');
        Schema::dropIfExists('incidencias');
        Schema::dropIfExists('consecutivos_ticket');
    }
};
