<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidencias (HU-3.2, RN-033, RN-042) y solicitudes de aclaración (HU-4.3, HU-5.1).
     */
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->nullable()->constrained('incidencias')->restrictOnDelete();
            $table->string('etapa', 20);
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('nombre_original', 255);
            $table->string('disco', 30);
            $table->string('ruta_storage', 255);
            $table->string('mime_type', 60);
            $table->unsignedInteger('tamano_bytes');
            $table->string('hash_sha256', 64);
            $table->foreignId('usuario_carga_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['incidencia_id', 'etapa']);
            $table->index(['usuario_carga_id', 'incidencia_id']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });

        Schema::create('solicitudes_aclaracion', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incidencia_id')->constrained('incidencias')->restrictOnDelete();
            $table->foreignId('gestor_solicitante_id')->constrained('users')->restrictOnDelete();
            $table->string('pregunta_gestor', 1000);
            $table->timestamp('fecha_solicitud');
            $table->timestamp('fecha_limite_respuesta');
            $table->string('respuesta_reportante', 500)->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->foreignId('respondida_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('estado', 15)->default('PENDIENTE');
            $table->timestamps();
            $table->index(['estado', 'fecha_limite_respuesta']);
            $table->index('incidencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_aclaracion');
        Schema::dropIfExists('evidencias');
    }
};
