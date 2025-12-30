<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Precios Base
 *
 * Gestiona los precios base de artículos por sucursal, forma de venta y canal.
 * Permite configurar precios diferentes según:
 * - Sucursal (obligatorio)
 * - Forma de venta (local, delivery, take away) - opcional
 * - Canal de venta (POS, salón, web, etc.) - opcional
 *
 * Búsqueda por especificidad:
 * 1. Precio con forma_venta + canal específicos
 * 2. Precio solo con forma_venta
 * 3. Precio solo con canal
 * 4. Precio por defecto (ambos NULL)
 * 5. Si no existe → usar articulo.precio_base
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('precios_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('forma_venta_id')->nullable()->comment('NULL = aplica a todas las formas');
            $table->unsignedBigInteger('canal_venta_id')->nullable()->comment('NULL = aplica a todos los canales');
            $table->decimal('precio', 12, 2)->comment('Precio del artículo');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->date('vigencia_desde')->nullable()->comment('Fecha desde la cual aplica');
            $table->date('vigencia_hasta')->nullable()->comment('Fecha hasta la cual aplica');
            $table->timestamps();

            // Clave única compuesta
            $table->unique(
                ['articulo_id', 'sucursal_id', 'forma_venta_id', 'canal_venta_id'],
                'unique_articulo_sucursal_forma_canal'
            );

            // Foreign keys
            $table->foreign('articulo_id', 'fk_precios_base_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_precios_base_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            $table->foreign('forma_venta_id', 'fk_precios_base_forma_venta')
                  ->references('id')
                  ->on('formas_venta')
                  ->onDelete('cascade');

            $table->foreign('canal_venta_id', 'fk_precios_base_canal_venta')
                  ->references('id')
                  ->on('canales_venta')
                  ->onDelete('cascade');

            // Índices para búsquedas rápidas
            $table->index(['articulo_id', 'sucursal_id', 'activo'], 'idx_articulo_sucursal_activo');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'idx_vigencia');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('precios_base');
    }
};
