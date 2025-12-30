<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Condiciones de Lista de Precios
 *
 * Define las condiciones que deben cumplirse para que una lista de precios aplique.
 * Una lista puede tener MÚLTIPLES condiciones (AND lógico entre todas).
 *
 * TIPOS DE CONDICIÓN:
 * - por_forma_pago: Solo si se paga con forma de pago específica
 * - por_forma_venta: Solo para forma de venta específica (local/delivery/takeaway)
 * - por_canal: Solo para canal específico (POS/salón/web)
 * - por_total_compra: Se aplica si total de compra está en rango
 *
 * NOTA: Las condiciones por artículo y categoría se manejan en lista_precio_articulos,
 * ya que definen QUÉ artículos participan, no CUÁNDO aplica la lista.
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
        Schema::connection('pymes_tenant')->create('lista_precio_condiciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lista_precio_id');

            $table->enum('tipo_condicion', [
                'por_forma_pago',
                'por_forma_venta',
                'por_canal',
                'por_total_compra'
            ])->comment('Tipo de condición a evaluar');

            // Relaciones (solo se usa la que corresponda según tipo_condicion)
            $table->unsignedBigInteger('forma_pago_id')->nullable();
            $table->unsignedBigInteger('forma_venta_id')->nullable();
            $table->unsignedBigInteger('canal_venta_id')->nullable();

            // Rangos para condiciones de monto
            $table->decimal('monto_minimo', 12, 2)->nullable()->comment('Monto mínimo de compra');
            $table->decimal('monto_maximo', 12, 2)->nullable()->comment('Monto máximo de compra');

            $table->timestamps();

            // Foreign keys (sin nombres explícitos para multi-tenant)
            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('listas_precios')
                  ->onDelete('cascade');

            $table->foreign('forma_pago_id')
                  ->references('id')
                  ->on('formas_pago')
                  ->onDelete('cascade');

            $table->foreign('forma_venta_id')
                  ->references('id')
                  ->on('formas_venta')
                  ->onDelete('cascade');

            $table->foreign('canal_venta_id')
                  ->references('id')
                  ->on('canales_venta')
                  ->onDelete('cascade');

            // Índices
            $table->index(['lista_precio_id', 'tipo_condicion'], 'lpc_lista_tipo_idx');
            $table->index('forma_pago_id', 'lpc_fpago_idx');
            $table->index('forma_venta_id', 'lpc_fventa_idx');
            $table->index('canal_venta_id', 'lpc_canal_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('lista_precio_condiciones');
    }
};
