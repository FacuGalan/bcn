<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo 'activo' a la tabla users
 *
 * Este campo permite activar/desactivar usuarios sin eliminarlos.
 * Un usuario desactivado no podrá iniciar sesión en el sistema.
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->boolean('activo')
                ->default(true)
                ->after('password_visible')
                ->comment('Indica si el usuario está activo (puede iniciar sesión)');

            // Índice para búsquedas rápidas de usuarios activos
            $table->index('activo', 'idx_users_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_activo');
            $table->dropColumn('activo');
        });
    }
};
