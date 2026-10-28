<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jerarquía de 4 niveles Sede -> Edificio -> Piso -> Espacio físico (RN-003, HU-11.1).
     */
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->string('direccion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('edificios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['sede_id', 'codigo']);
        });

        Schema::create('pisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edificio_id')->constrained('edificios')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->smallInteger('nivel')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['edificio_id', 'codigo']);
        });

        Schema::create('espacios_fisicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piso_id')->constrained('pisos')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->string('tipo', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['piso_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios_fisicos');
        Schema::dropIfExists('pisos');
        Schema::dropIfExists('edificios');
        Schema::dropIfExists('sedes');
    }
};
