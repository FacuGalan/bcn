<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Ventas
 *
 * Registra las ventas de cada sucursal.
 * Cada venta pertenece a una sucursal específica.
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
        Schema::connection('pymes_tenant')->create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('caja_id')->nullable()->comment('Caja donde se registró la venta');
            $table->string('numero_comprobante', 50)->comment('Número de factura/ticket');
            $table->enum('tipo_comprobante', ['factura_a', 'factura_b', 'factura_c', 'ticket', 'nota_credito', 'nota_debito']);
            $table->date('fecha');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['pendiente', 'pagada', 'parcial', 'anulada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('user_id')->comment('Usuario que realizó la venta');
            $table->timestamps();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_ventas_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('restrict');

            $table->foreign('cliente_id', 'fk_ventas_cliente')
                  ->references('id')
                  ->on('clientes')
                  ->onDelete('restrict');

            $table->foreign('caja_id', 'fk_ventas_caja')
                  ->references('id')
                  ->on('cajas')
                  ->onDelete('set null');

            // Índices
            $table->unique(['sucursal_id', 'numero_comprobante'], 'unique_numero_comprobante_sucursal');
            $table->index(['sucursal_id', 'fecha'], 'idx_sucursal_fecha');
            $table->index('cliente_id', 'idx_cliente');
            $table->index('estado', 'idx_estado');
            $table->index('fecha', 'idx_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('ventas');
    }
};
