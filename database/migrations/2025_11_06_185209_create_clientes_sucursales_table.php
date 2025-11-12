<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Pivot Clientes-Sucursales
 *
 * Gestiona las características específicas de cada cliente por sucursal.
 * Permite que un cliente tenga diferentes condiciones en diferentes sucursales.
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
        Schema::connection('pymes_tenant')->create('clientes_sucursales', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('lista_precio_id')->nullable()->comment('Lista de precios asignada');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->comment('Descuento % por defecto');
            $table->decimal('limite_credito', 10, 2)->default(0)->comment('Límite de crédito');
            $table->decimal('saldo_actual', 10, 2)->default(0)->comment('Saldo de cuenta corriente');
            $table->boolean('activo')->default(true)->comment('Si está activo en esta sucursal');
            $table->timestamps();

            // Clave primaria compuesta
            $table->primary(['cliente_id', 'sucursal_id'], 'pk_cliente_sucursal');

            // Foreign keys
            $table->foreign('cliente_id', 'fk_clientes_sucursales_cliente')
                  ->references('id')
                  ->on('clientes')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_clientes_sucursales_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índice
            $table->index('sucursal_id', 'idx_sucursal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('clientes_sucursales');
    }
};
