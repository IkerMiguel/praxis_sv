<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de roles R01-R07 (D03), especialidades técnicas y su relación multi-rol con usuarios.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre', 80);
            $table->string('descripcion', 500)->nullable();
            $table->string('ruta_inicio', 120);
            $table->unsignedTinyInteger('orden');
            $table->timestamps();
        });

        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono', 30)->nullable()->after('email');
            $table->boolean('activo')->default(true)->after('password');
            $table->unsignedSmallInteger('intentos_fallidos')->default(0)->after('activo');
            $table->timestamp('ultimo_intento_fallido_en')->nullable()->after('intentos_fallidos');
            $table->timestamp('bloqueado_hasta')->nullable()->after('ultimo_intento_fallido_en');
            $table->timestamp('ultimo_acceso_en')->nullable()->after('bloqueado_hasta');
        });

        Schema::create('usuario_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('asignado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->primary(['user_id', 'rol_id']);
            $table->index('rol_id');
        });

        Schema::create('usuario_especialidades', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $table->primary(['user_id', 'especialidad_id']);
            $table->index('especialidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_especialidades');
        Schema::dropIfExists('usuario_roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'activo', 'intentos_fallidos', 'ultimo_intento_fallido_en', 'bloqueado_hasta', 'ultimo_acceso_en']);
        });
        Schema::dropIfExists('especialidades');
        Schema::dropIfExists('roles');
    }
};
