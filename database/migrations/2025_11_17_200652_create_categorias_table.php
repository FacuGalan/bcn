<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Categorías
 *
 * Catálogo de categorías de artículos para organización y aplicación de promociones.
 * Cada comercio tiene su propio catálogo de categorías.
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
        Schema::connection('pymes_tenant')->create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre de la categoría');
            $table->string('codigo', 50)->nullable()->comment('Código alfanumérico opcional');
            $table->text('descripcion')->nullable()->comment('Descripción de la categoría');
            $table->string('color', 7)->nullable()->comment('Color en hex para UI (#FF5733)');
            $table->boolean('activo')->default(true)->comment('Si está activa');
            $table->timestamps();

            // Índices
            $table->index('nombre', 'idx_nombre');
            $table->index('codigo', 'idx_codigo');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('categorias');
    }
};
