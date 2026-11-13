<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notificaciones en plataforma (HU-2.5) y bitácora de auditoría append-only (D10, HU-10.2).
     */
    public function up(): void
    {
        Schema::create('notificaciones_plataforma', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->nullable()->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('usuario_destino_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo', 150);
            $table->string('mensaje', 1000);
            $table->boolean('leida')->default(false);
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('fecha_lectura')->nullable();
            $table->index(['usuario_destino_id', 'leida', 'fecha_envio']);
        });

        Schema::create('eventos_auditoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('incidencia_id')->nullable()->constrained('incidencias')->restrictOnDelete();
            $table->string('tipo_evento', 50);
            $table->string('categoria', 15);
            $table->string('visibilidad', 10)->default('INTERNO');
            $table->timestamp('fecha_hora', 6);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('rol_codigo', 10)->nullable();
            $table->string('estado_anterior', 3)->nullable();
            $table->string('estado_nuevo', 3)->nullable();
            $table->text('motivo')->nullable();
            $table->jsonb('evidencias')->nullable();
            $table->jsonb('datos')->nullable();
            $table->string('entidad_tipo', 60)->nullable();
            $table->string('entidad_id', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->index(['incidencia_id', 'fecha_hora']);
            $table->index(['tipo_evento', 'fecha_hora']);
            $table->index(['entidad_tipo', 'entidad_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION eventos_auditoria_inmutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'eventos_auditoria es append-only: % no permitido (D10)', TG_OP
                        USING ERRCODE = 'insufficient_privilege';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER eventos_auditoria_bloquear_modificacion
                    BEFORE UPDATE OR DELETE ON eventos_auditoria
                    FOR EACH ROW EXECUTE FUNCTION eventos_auditoria_inmutable();

                CREATE TRIGGER eventos_auditoria_bloquear_truncate
                    BEFORE TRUNCATE ON eventos_auditoria
                    FOR EACH STATEMENT EXECUTE FUNCTION eventos_auditoria_inmutable();
            SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER eventos_auditoria_bloquear_update BEFORE UPDATE ON eventos_auditoria
                BEGIN SELECT RAISE(ABORT, 'eventos_auditoria es append-only: UPDATE no permitido (D10)'); END;
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER eventos_auditoria_bloquear_delete BEFORE DELETE ON eventos_auditoria
                BEGIN SELECT RAISE(ABORT, 'eventos_auditoria es append-only: DELETE no permitido (D10)'); END;
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS eventos_auditoria_bloquear_modificacion ON eventos_auditoria');
            DB::unprepared('DROP TRIGGER IF EXISTS eventos_auditoria_bloquear_truncate ON eventos_auditoria');
            DB::unprepared('DROP FUNCTION IF EXISTS eventos_auditoria_inmutable()');
        }

        Schema::dropIfExists('eventos_auditoria');
        Schema::dropIfExists('notificaciones_plataforma');
    }
};
