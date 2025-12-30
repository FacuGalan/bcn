<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar Soft Deletes a Categorías y Artículos
 *
 * Implementa borrado lógico para preservar datos históricos
 * y evitar inconsistencias en reportes futuros.
 *
 * Las tablas afectadas son:
 * - categorias: Clasificación de artículos
 * - articulos: Catálogo maestro de productos
 *
 * Nota: La tabla listas_precios ya tiene soft deletes desde su creación.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar soft deletes a categorias
        Schema::connection('pymes_tenant')->table('categorias', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Agregar soft deletes a articulos
        Schema::connection('pymes_tenant')->table('articulos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Quitar soft deletes de categorias
        Schema::connection('pymes_tenant')->table('categorias', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Quitar soft deletes de articulos
        Schema::connection('pymes_tenant')->table('articulos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
