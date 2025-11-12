<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo password_visible a la tabla users
 *
 * Este campo almacena la contraseña de forma cifrada (NO texto plano) para que
 * administradores y programadores puedan verla cuando sea necesario.
 *
 * NOTA DE SEGURIDAD:
 * Aunque está cifrada, mantener contraseñas "visibles" es un riesgo de seguridad.
 * Se recomienda:
 * - Limitar acceso solo a super administradores
 * - Registrar en logs cada vez que se visualice
 * - Considerar políticas de rotación de contraseñas
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones para agregar el campo password_visible
     *
     * Campo agregado:
     * - password_visible: Contraseña cifrada con Laravel encryption (texto nullable)
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->text('password_visible')
                ->nullable()
                ->after('password')
                ->comment('Contraseña cifrada para visualización administrativa');
        });
    }

    /**
     * Revierte las migraciones eliminando el campo password_visible
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->dropColumn('password_visible');
        });
    }
};
