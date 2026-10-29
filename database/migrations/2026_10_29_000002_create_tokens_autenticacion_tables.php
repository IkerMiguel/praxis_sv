<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refresh tokens rotativos (D15) y tokens de recuperación de contraseña (HU-1.4).
     */
    public function up(): void
    {
        Schema::create('tokens_refresco', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('familia');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revocado_en']);
            $table->index('familia');
        });

        Schema::create('tokens_recuperacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('fecha_expiracion');
            $table->boolean('usado')->default(false);
            $table->timestamp('fecha_uso')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'usado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens_recuperacion');
        Schema::dropIfExists('tokens_refresco');
    }
};
