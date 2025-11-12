<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de estado a cajas
 *
 * Agrega campos para controlar apertura/cierre de cajas:
 * - estado (abierta, cerrada)
 * - fecha_apertura
 * - fecha_cierre
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
        Schema::connection('pymes_tenant')->table('cajas', function (Blueprint $table) {
            // Campo de estado
            $table->enum('estado', ['abierta', 'cerrada'])
                  ->default('cerrada')
                  ->after('activo')
                  ->comment('Estado actual de la caja');

            // Fechas de apertura y cierre
            $table->timestamp('fecha_apertura')
                  ->nullable()
                  ->after('estado')
                  ->comment('Fecha y hora de apertura');

            $table->timestamp('fecha_cierre')
                  ->nullable()
                  ->after('fecha_apertura')
                  ->comment('Fecha y hora de cierre');

            // Índice para consultas por estado
            $table->index('estado', 'idx_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('cajas', function (Blueprint $table) {
            $table->dropIndex('idx_estado');
            $table->dropColumn(['estado', 'fecha_apertura', 'fecha_cierre']);
        });
    }
};
