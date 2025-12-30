<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Formas de Venta
 *
 * Define las diferentes formas de venta disponibles (local, delivery, take away, etc.)
 * Nivel comercio - Las sucursales configuran cuáles están disponibles para ellas.
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
        Schema::connection('pymes_tenant')->create('formas_venta', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre de la forma de venta');
            $table->string('codigo', 50)->nullable()->comment('Código alfanumérico');
            $table->text('descripcion')->nullable()->comment('Descripción');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->timestamps();

            // Índices
            $table->index('nombre', 'idx_nombre');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('formas_venta');
    }
};
