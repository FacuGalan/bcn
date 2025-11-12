<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Agregar sucursal_id a model_has_roles
 *
 * Modifica la tabla de Spatie Permission para soportar roles por sucursal.
 * Un usuario puede tener diferentes roles en diferentes sucursales.
 * sucursal_id NULL = acceso a todas las sucursales.
 *
 * FASE 1 - Sistema Multi-Sucursal
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero eliminar foreign key
        Schema::connection('pymes_tenant')->table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });

        // Luego modificar la estructura
        Schema::connection('pymes_tenant')->table('model_has_roles', function (Blueprint $table) {
            // Eliminar primary key existente
            $table->dropPrimary(['role_id', 'model_id', 'model_type']);

            // Agregar columna sucursal_id (0 = acceso a todas las sucursales)
            $table->unsignedBigInteger('sucursal_id')->default(0)->after('model_type')
                  ->comment('0 = acceso a todas las sucursales, >0 = sucursal específica');

            // Crear nueva primary key incluyendo sucursal_id
            $table->primary(['role_id', 'model_id', 'model_type', 'sucursal_id']);

            // Agregar índice
            $table->index('sucursal_id', 'idx_sucursal');
        });

        // Recrear foreign key
        Schema::connection('pymes_tenant')->table('model_has_roles', function (Blueprint $table) {
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('model_has_roles', function (Blueprint $table) {
            // Eliminar primary key con sucursal_id
            $table->dropPrimary(['role_id', 'model_id', 'model_type', 'sucursal_id']);

            // Eliminar índice y columna
            $table->dropIndex('idx_sucursal');
            $table->dropColumn('sucursal_id');

            // Restaurar primary key original
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }
};
