<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla user_cajas
 *
 * Define qué cajas puede usar cada usuario en cada sucursal.
 * Similar al sistema de user_sucursales, pero a nivel de caja.
 *
 * ESTRUCTURA:
 * - Un usuario puede tener acceso a múltiples cajas
 * - Las cajas pertenecen a una sucursal específica
 * - Al cambiar de sucursal, se resetea a la primera caja de la nueva sucursal
 *
 * CASO ESPECIAL:
 * - Si NO hay registros para un usuario, NO tiene acceso a ninguna caja
 *   (debe ser asignado explícitamente por un Super Admin)
 *
 * FASE 4 - Sistema de Cajas por Usuario
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = 'pymes_tenant';

        if (!Schema::connection($connection)->hasTable('user_cajas')) {
            Schema::connection($connection)->create('user_cajas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('ID del usuario en tabla config.users');
                $table->unsignedBigInteger('caja_id')->comment('ID de la caja');
                $table->unsignedBigInteger('sucursal_id')->comment('ID de la sucursal (redundante pero útil para queries)');
                $table->timestamps();

                // Índices para optimizar consultas
                $table->index('user_id', 'idx_user');
                $table->index('caja_id', 'idx_caja');
                $table->index('sucursal_id', 'idx_sucursal');
                $table->index(['user_id', 'sucursal_id'], 'idx_user_sucursal');

                // Unique constraint: un usuario no puede tener la misma caja duplicada
                $table->unique(['user_id', 'caja_id'], 'uk_user_caja');
            });

            // Agregar foreign keys después si las tablas existen
            if (Schema::connection($connection)->hasTable('cajas')) {
                Schema::connection($connection)->table('user_cajas', function (Blueprint $table) {
                    $table->foreign('caja_id', 'fk_user_cajas_caja')
                          ->references('id')
                          ->on('cajas')
                          ->onDelete('cascade');
                });
            }

            if (Schema::connection($connection)->hasTable('sucursales')) {
                Schema::connection($connection)->table('user_cajas', function (Blueprint $table) {
                    $table->foreign('sucursal_id', 'fk_user_cajas_sucursal')
                          ->references('id')
                          ->on('sucursales')
                          ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('user_cajas');
    }
};
