<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Pivot Formas de Pago - Sucursales
 *
 * Controla qué formas de pago están disponibles en cada sucursal.
 * Funciona similar a articulos_sucursales.
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
        Schema::connection('pymes_tenant')->create('formas_pago_sucursales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forma_pago_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->boolean('activo')->default(true)->comment('Si está disponible en esta sucursal');
            $table->timestamps();

            // Clave única
            $table->unique(['forma_pago_id', 'sucursal_id'], 'unique_forma_pago_sucursal');

            // Foreign keys
            $table->foreign('forma_pago_id', 'fk_formas_pago_sucursales_forma')
                  ->references('id')
                  ->on('formas_pago')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_formas_pago_sucursales_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index(['sucursal_id', 'activo'], 'idx_sucursal_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('formas_pago_sucursales');
    }
};
