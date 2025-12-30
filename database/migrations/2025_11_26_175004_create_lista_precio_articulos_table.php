<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Detalle de Artículos en Lista de Precios
 *
 * Define qué artículos o categorías participan en una lista de precios
 * y cuál es su precio/ajuste específico.
 *
 * FUNCIONAMIENTO:
 * - Si articulo_id está definido: aplica a ese artículo específico
 * - Si categoria_id está definido: aplica a todos los artículos de esa categoría
 * - precio_fijo: Si se define, pisa completamente al precio base del artículo
 * - ajuste_porcentaje: Si se define, aplica sobre el precio base del artículo
 * - Si ambos son NULL: usa el ajuste_porcentaje del encabezado de la lista
 *
 * JERARQUÍA DE BÚSQUEDA:
 * 1. Buscar por articulo_id exacto en el detalle
 * 2. Si no encuentra, buscar por categoria_id del artículo
 * 3. Si no encuentra, usar ajuste del encabezado de la lista
 *
 * PRECIO VS AJUSTE:
 * - precio_fijo tiene prioridad sobre ajuste_porcentaje
 * - ajuste_porcentaje se calcula sobre articulo.precio_base
 * - precio_base_original guarda el precio del artículo al momento de crear (referencia)
 *
 * FASE 2 - Sistema de Listas de Precios
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('lista_precio_articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lista_precio_id');

            // Referencia: artículo O categoría (no ambos)
            $table->unsignedBigInteger('articulo_id')->nullable()
                  ->comment('ID del artículo específico');
            $table->unsignedBigInteger('categoria_id')->nullable()
                  ->comment('ID de la categoría (aplica a todos sus artículos)');

            // Precio y ajuste
            $table->decimal('precio_fijo', 12, 2)->nullable()
                  ->comment('Precio fijo que pisa al precio base (opcional)');
            $table->decimal('ajuste_porcentaje', 8, 2)->nullable()
                  ->comment('Porcentaje de ajuste sobre precio base (+ recargo, - descuento)');

            // Información de referencia
            $table->decimal('precio_base_original', 12, 2)->nullable()
                  ->comment('Precio base del artículo al momento de crear el registro');

            $table->timestamps();

            // Foreign keys (sin nombres explícitos para multi-tenant)
            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('listas_precios')
                  ->onDelete('cascade');

            $table->foreign('articulo_id')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('categoria_id')
                  ->references('id')
                  ->on('categorias')
                  ->onDelete('cascade');

            // Índices
            $table->index('lista_precio_id', 'lpa_lista_idx');
            $table->index('articulo_id', 'lpa_art_idx');
            $table->index('categoria_id', 'lpa_cat_idx');

            // Restricciones únicas
            $table->unique(['lista_precio_id', 'articulo_id'], 'lpa_lista_art_uniq');
            $table->unique(['lista_precio_id', 'categoria_id'], 'lpa_lista_cat_uniq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('lista_precio_articulos');
    }
};
