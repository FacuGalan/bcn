<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Ventas Detalle
 *
 * Registra los ítems de cada venta.
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
        Schema::connection('pymes_tenant')->create('ventas_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('venta_id', 'fk_ventas_detalle_venta')
                  ->references('id')
                  ->on('ventas')
                  ->onDelete('cascade');

            $table->foreign('articulo_id', 'fk_ventas_detalle_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('restrict');

            // Índices
            $table->index('venta_id', 'idx_venta');
            $table->index('articulo_id', 'idx_articulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('ventas_detalle');
    }
};
