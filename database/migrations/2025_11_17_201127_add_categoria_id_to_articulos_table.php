<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar categoria_id a tabla articulos
 *
 * Agrega la relación con categorías si no existe ya.
 * Esta migración es segura: verifica si la columna existe antes de agregarla.
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
        $connection = 'pymes_tenant';

        try {
            Schema::connection($connection)->table('articulos', function (Blueprint $table) {
                $table->unsignedBigInteger('categoria_id')->nullable()->after('descripcion')
                      ->comment('Categoría del artículo');

                // Foreign key
                $table->foreign('categoria_id', 'fk_articulos_categoria')
                      ->references('id')
                      ->on('categorias')
                      ->onDelete('set null');

                // Índice
                $table->index('categoria_id', 'idx_articulos_categoria');
            });
        } catch (\Exception $e) {
            // La columna ya existe, ignorar
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'pymes_tenant';

        try {
            Schema::connection($connection)->table('articulos', function (Blueprint $table) {
                $table->dropForeign('fk_articulos_categoria');
                $table->dropIndex('idx_articulos_categoria');
                $table->dropColumn('categoria_id');
            });
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }
};
