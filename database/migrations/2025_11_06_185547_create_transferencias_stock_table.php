<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Transferencias de Stock
 *
 * Registra las transferencias de stock entre sucursales.
 * Incluye flujo completo con estados y aprobaciones.
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
        Schema::connection('pymes_tenant')->create('transferencias_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_origen_id');
            $table->unsignedBigInteger('sucursal_destino_id');
            $table->decimal('cantidad', 10, 2);
            $table->enum('estado', ['pendiente', 'aprobada', 'en_transito', 'recibida', 'rechazada'])->default('pendiente');
            $table->enum('tipo', ['simple', 'venta_compra_fiscal'])->default('simple');
            $table->unsignedBigInteger('venta_id')->nullable()->comment('Si es venta/compra fiscal');
            $table->unsignedBigInteger('compra_id')->nullable()->comment('Si es venta/compra fiscal');
            $table->unsignedBigInteger('solicitado_por_user_id');
            $table->unsignedBigInteger('aprobado_por_user_id')->nullable();
            $table->unsignedBigInteger('recibido_por_user_id')->nullable();
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('articulo_id', 'fk_transferencias_stock_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('restrict');

            $table->foreign('sucursal_origen_id', 'fk_transferencias_stock_origen')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('restrict');

            $table->foreign('sucursal_destino_id', 'fk_transferencias_stock_destino')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('restrict');

            $table->foreign('venta_id', 'fk_transferencias_stock_venta')
                  ->references('id')
                  ->on('ventas')
                  ->onDelete('set null');

            $table->foreign('compra_id', 'fk_transferencias_stock_compra')
                  ->references('id')
                  ->on('compras')
                  ->onDelete('set null');

            // Índices
            $table->index(['sucursal_origen_id', 'sucursal_destino_id'], 'idx_origen_destino');
            $table->index('estado', 'idx_estado');
            $table->index('articulo_id', 'idx_articulo');
            $table->index('fecha_solicitud', 'idx_fecha_solicitud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('transferencias_stock');
    }
};
