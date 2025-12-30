<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar lista de precios a clientes
 *
 * Permite asignar una lista de precios predeterminada a cada cliente.
 * Un cliente solo puede tener UNA lista de precios asignada.
 *
 * FUNCIONAMIENTO:
 * - Si el cliente tiene lista_precio_id, se usa esa lista al venderle
 * - El vendedor puede seleccionar otra lista manualmente (pisa la del cliente)
 * - Si no tiene lista asignada, se busca la lista que cumpla condiciones
 *
 * FASE 2 - Sistema de Listas de Precios
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::connection('pymes_tenant')->table('clientes', function (Blueprint $table) {
                $table->unsignedBigInteger('lista_precio_id')->nullable()
                      ->after('activo')
                      ->comment('Lista de precios asignada al cliente');

                $table->foreign('lista_precio_id')
                      ->references('id')
                      ->on('listas_precios')
                      ->onDelete('set null');

                $table->index('lista_precio_id', 'cli_lprecio_idx');
            });
        } catch (\Exception $e) {
            // Column already exists, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('clientes', function (Blueprint $table) {
            $table->dropForeign(['lista_precio_id']);
            $table->dropIndex('cli_lprecio_idx');
            $table->dropColumn('lista_precio_id');
        });
    }
};
