<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Canales de Venta
 *
 * Define los canales por los cuales se realizan ventas (POS, salón, mostrador, pedidos, web, etc.)
 * Nivel comercio - Permite tener precios diferentes por canal.
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
        Schema::connection('pymes_tenant')->create('canales_venta', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre del canal');
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
        Schema::connection('pymes_tenant')->dropIfExists('canales_venta');
    }
};
