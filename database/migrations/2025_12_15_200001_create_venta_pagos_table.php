<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Venta Pagos
 *
 * Registra el desglose de formas de pago utilizadas en cada venta.
 * Permite pagos mixtos con múltiples formas de pago.
 * Guarda ajustes (recargos/descuentos) y cuotas para reportes.
 *
 * Sistema de Precios Dinámico - Formas de Pago
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('forma_pago_id');
            $table->unsignedBigInteger('concepto_pago_id')->nullable()->comment('Concepto usado (para mixtas)');

            // Montos
            $table->decimal('monto_base', 12, 2)->comment('Monto antes de ajustes');
            $table->decimal('ajuste_porcentaje', 6, 2)->default(0)->comment('Ajuste aplicado (+ recargo, - descuento)');
            $table->decimal('monto_ajuste', 12, 2)->default(0)->comment('Monto del ajuste');
            $table->decimal('monto_final', 12, 2)->comment('Monto final después de ajustes');

            // Para efectivo
            $table->decimal('monto_recibido', 12, 2)->nullable()->comment('Monto recibido (efectivo)');
            $table->decimal('vuelto', 12, 2)->nullable()->comment('Vuelto entregado');

            // Para tarjetas con cuotas
            $table->unsignedTinyInteger('cuotas')->nullable()->comment('Cantidad de cuotas');
            $table->decimal('recargo_cuotas_porcentaje', 6, 2)->nullable()->comment('Recargo por cuotas');
            $table->decimal('recargo_cuotas_monto', 12, 2)->nullable()->comment('Monto recargo por cuotas');
            $table->decimal('monto_cuota', 12, 2)->nullable()->comment('Valor de cada cuota');

            // Referencia
            $table->string('referencia', 100)->nullable()->comment('Nro autorización, voucher, etc');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('venta_id', 'fk_venta_pagos_venta')
                  ->references('id')
                  ->on('ventas')
                  ->onDelete('cascade');

            $table->foreign('forma_pago_id', 'fk_venta_pagos_forma_pago')
                  ->references('id')
                  ->on('formas_pago')
                  ->onDelete('restrict');

            $table->foreign('concepto_pago_id', 'fk_venta_pagos_concepto')
                  ->references('id')
                  ->on('conceptos_pago')
                  ->onDelete('set null');

            // Índices
            $table->index('venta_id', 'idx_venta_pagos_venta');
            $table->index('forma_pago_id', 'idx_venta_pagos_forma');
            $table->index('concepto_pago_id', 'idx_venta_pagos_concepto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('venta_pagos');
    }
};
