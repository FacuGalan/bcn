<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo de sesiones concurrentes a la tabla users
 *
 * Agrega el campo max_concurrent_sessions que permite controlar en cuántos
 * dispositivos simultáneos puede estar logueado un usuario.
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones para agregar el campo max_concurrent_sessions
     *
     * Campo agregado:
     * - max_concurrent_sessions: Número máximo de sesiones simultáneas permitidas (default: 1)
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_concurrent_sessions')
                ->default(1)
                ->after('password')
                ->comment('Número máximo de dispositivos donde puede estar logueado simultáneamente');
        });
    }

    /**
     * Revierte las migraciones eliminando el campo max_concurrent_sessions
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->dropColumn('max_concurrent_sessions');
        });
    }
};
