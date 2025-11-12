<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Compras Detalle
 *
 * Registra los ítems de cada compra.
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
        Schema::connection('pymes_tenant')->create('compras_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('compra_id', 'fk_compras_detalle_compra')
                  ->references('id')
                  ->on('compras')
                  ->onDelete('cascade');

            $table->foreign('articulo_id', 'fk_compras_detalle_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('restrict');

            // Índices
            $table->index('compra_id', 'idx_compra');
            $table->index('articulo_id', 'idx_articulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('compras_detalle');
    }
};
