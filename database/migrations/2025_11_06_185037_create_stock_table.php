<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Stock
 *
 * Gestiona las cantidades de stock de cada artículo por sucursal.
 * Cada artículo tiene un registro de stock independiente en cada sucursal.
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
        Schema::connection('pymes_tenant')->create('stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->decimal('cantidad', 10, 2)->default(0)->comment('Cantidad disponible');
            $table->decimal('cantidad_minima', 10, 2)->nullable()->comment('Stock mínimo');
            $table->decimal('cantidad_maxima', 10, 2)->nullable()->comment('Stock máximo');
            $table->timestamp('ultima_actualizacion')->nullable()->comment('Última actualización de stock');
            $table->timestamps();

            // Índice único
            $table->unique(['articulo_id', 'sucursal_id'], 'unique_articulo_sucursal');

            // Foreign keys
            $table->foreign('articulo_id', 'fk_stock_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_stock_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index('sucursal_id', 'idx_sucursal');
            $table->index('cantidad', 'idx_cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('stock');
    }
};
