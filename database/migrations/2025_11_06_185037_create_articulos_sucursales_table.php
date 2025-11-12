<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Pivot Artículos-Sucursales
 *
 * Controla qué artículos están disponibles en qué sucursales.
 * Un artículo puede estar disponible en algunas sucursales y no en otras.
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
        Schema::connection('pymes_tenant')->create('articulos_sucursales', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->boolean('activo')->default(true)->comment('Si está disponible en esta sucursal');
            $table->timestamps();

            // Clave primaria compuesta
            $table->primary(['articulo_id', 'sucursal_id'], 'pk_articulo_sucursal');

            // Foreign keys
            $table->foreign('articulo_id', 'fk_articulos_sucursales_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_articulos_sucursales_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índice
            $table->index(['sucursal_id', 'activo'], 'idx_sucursal_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('articulos_sucursales');
    }
};
