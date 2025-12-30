<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Promociones - Condiciones
 *
 * Define las condiciones que deben cumplirse para que aplique una promoción.
 * Una promoción puede tener MÚLTIPLES condiciones (AND lógico entre todas).
 *
 * TIPOS DE CONDICIÓN:
 * - por_articulo: Se aplica a un artículo específico
 * - por_categoria: Se aplica a todos los artículos de una categoría
 * - por_forma_pago: Solo si se paga con forma de pago específica
 * - por_forma_venta: Solo para forma de venta específica (local/delivery/takeaway)
 * - por_canal: Solo para canal específico (POS/salón/web)
 * - por_cantidad: Se aplica si cantidad está en rango
 * - por_total_compra: Se aplica si total de compra está en rango
 *
 * ESCALABILIDAD:
 * Para agregar nuevos tipos de condiciones en el futuro, solo agregar:
 * 1. Nuevo valor al ENUM 'tipo_condicion'
 * 2. Nuevas columnas si es necesario
 * 3. Lógica de validación en PrecioService
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
        Schema::connection('pymes_tenant')->create('promociones_condiciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_id');

            $table->enum('tipo_condicion', [
                'por_articulo',
                'por_categoria',
                'por_forma_pago',
                'por_forma_venta',
                'por_canal',
                'por_cantidad',
                'por_total_compra'
            ])->comment('Tipo de condición a evaluar');

            // Relaciones (solo se usa la que corresponda según tipo_condicion)
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->unsignedBigInteger('forma_pago_id')->nullable();
            $table->unsignedBigInteger('forma_venta_id')->nullable();
            $table->unsignedBigInteger('canal_venta_id')->nullable();

            // Rangos para condiciones de cantidad/monto
            $table->decimal('cantidad_minima', 12, 3)->nullable()->comment('Cantidad mínima requerida');
            $table->decimal('cantidad_maxima', 12, 3)->nullable()->comment('Cantidad máxima permitida');
            $table->decimal('monto_minimo', 12, 2)->nullable()->comment('Monto mínimo de compra');
            $table->decimal('monto_maximo', 12, 2)->nullable()->comment('Monto máximo de compra');

            $table->timestamps();

            // Foreign keys
            $table->foreign('promocion_id', 'fk_promo_cond_promocion')
                  ->references('id')
                  ->on('promociones')
                  ->onDelete('cascade');

            $table->foreign('articulo_id', 'fk_promo_cond_articulo')
                  ->references('id')
                  ->on('articulos')
                  ->onDelete('cascade');

            $table->foreign('categoria_id', 'fk_promo_cond_categoria')
                  ->references('id')
                  ->on('categorias')
                  ->onDelete('cascade');

            $table->foreign('forma_pago_id', 'fk_promo_cond_forma_pago')
                  ->references('id')
                  ->on('formas_pago')
                  ->onDelete('cascade');

            $table->foreign('forma_venta_id', 'fk_promo_cond_forma_venta')
                  ->references('id')
                  ->on('formas_venta')
                  ->onDelete('cascade');

            $table->foreign('canal_venta_id', 'fk_promo_cond_canal')
                  ->references('id')
                  ->on('canales_venta')
                  ->onDelete('cascade');

            // Índices
            $table->index(['promocion_id', 'tipo_condicion'], 'idx_promocion_tipo');
            $table->index('articulo_id', 'idx_articulo');
            $table->index('categoria_id', 'idx_categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('promociones_condiciones');
    }
};
