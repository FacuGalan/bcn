<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla comprobante_fiscal_ventas
 *
 * Tabla pivot que relaciona comprobantes fiscales con ventas.
 * Permite:
 * - Un comprobante puede asociarse a múltiples ventas
 * - Una venta puede tener múltiples comprobantes (facturación parcial)
 *
 * El campo 'monto' indica cuánto de la venta está cubierto por este comprobante.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('comprobante_fiscal_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_fiscal_id');
            $table->unsignedBigInteger('venta_id');

            // Monto de la venta cubierto por este comprobante
            $table->decimal('monto', 12, 2)
                ->comment('Monto de la venta incluido en este comprobante');

            // Para notas de crédito: indica que anula parte de esta venta
            $table->boolean('es_anulacion')->default(false)
                ->comment('True si el comprobante anula (NC) esta venta');

            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('comprobante_fiscal_id', 'fk_cfv_comprobante')
                ->references('id')
                ->on('comprobantes_fiscales')
                ->onDelete('cascade');

            $table->foreign('venta_id', 'fk_cfv_venta')
                ->references('id')
                ->on('ventas')
                ->onDelete('cascade');

            // Índices
            $table->index('comprobante_fiscal_id', 'idx_cfv_comprobante');
            $table->index('venta_id', 'idx_cfv_venta');

            // Una venta solo puede aparecer una vez por comprobante
            $table->unique(['comprobante_fiscal_id', 'venta_id'], 'unique_cfv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('comprobante_fiscal_ventas');
    }
};
