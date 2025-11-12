<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Precios
 *
 * Gestiona los precios de artículos por sucursal y tipo de precio.
 * Permite tener diferentes precios en diferentes sucursales.
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
        Schema::connection('pymes_tenant')->create('precios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_id')->nullable()->comment('NULL = precio por defecto para todas');
            $table->unsignedBigInteger('tipo_precio_id')->comment('Local, Web, Mayorista, etc.');
            $table->decimal('precio', 10, 2)->comment('Precio del artículo');
            $table->date('vigencia_desde')->nullable()->comment('Fecha desde la cual aplica');
            $table->date('vigencia_hasta')->nullable()->comment('Fecha hasta la cual aplica');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->timestamps();

            // Foreign keys
            $table->foreign('articulo_id', 'fk_precios_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_precios_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index(['articulo_id', 'sucursal_id', 'tipo_precio_id'], 'idx_articulo_sucursal_tipo');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'idx_vigencia');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('precios');
    }
};
