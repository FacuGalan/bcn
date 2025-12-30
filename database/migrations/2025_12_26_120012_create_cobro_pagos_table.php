<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla cobro_pagos
 *
 * Registra el desglose de formas de pago utilizadas en cada cobro.
 * Similar a venta_pagos pero para cobros de cuenta corriente.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('cobro_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('forma_pago_id');
            $table->unsignedBigInteger('concepto_pago_id')->nullable()
                ->comment('Concepto usado (para formas mixtas)');

            // Montos
            $table->decimal('monto_base', 12, 2)
                ->comment('Monto antes de ajustes');
            $table->decimal('ajuste_porcentaje', 6, 2)->default(0)
                ->comment('Ajuste aplicado (+ recargo, - descuento)');
            $table->decimal('monto_ajuste', 12, 2)->default(0)
                ->comment('Monto del ajuste');
            $table->decimal('monto_final', 12, 2)
                ->comment('Monto final después de ajustes');

            // Para efectivo
            $table->decimal('monto_recibido', 12, 2)->nullable();
            $table->decimal('vuelto', 12, 2)->nullable();

            // Para tarjetas con cuotas
            $table->unsignedTinyInteger('cuotas')->nullable();
            $table->decimal('recargo_cuotas_porcentaje', 6, 2)->nullable();
            $table->decimal('recargo_cuotas_monto', 12, 2)->nullable();
            $table->decimal('monto_cuota', 12, 2)->nullable();

            // Referencia
            $table->string('referencia', 100)->nullable()
                ->comment('Nro autorización, voucher, etc');
            $table->text('observaciones')->nullable();

            // Si afecta caja
            $table->boolean('afecta_caja')->default(true);
            $table->unsignedBigInteger('movimiento_caja_id')->nullable();

            // Estado
            $table->enum('estado', ['activo', 'anulado'])->default('activo');

            $table->timestamps();

            // Foreign keys
            $table->foreign('cobro_id', 'fk_cp_cobro')
                ->references('id')
                ->on('cobros')
                ->onDelete('cascade');

            $table->foreign('forma_pago_id', 'fk_cp_forma_pago')
                ->references('id')
                ->on('formas_pago')
                ->onDelete('restrict');

            $table->foreign('concepto_pago_id', 'fk_cp_concepto')
                ->references('id')
                ->on('conceptos_pago')
                ->onDelete('set null');

            $table->foreign('movimiento_caja_id', 'fk_cp_mov_caja')
                ->references('id')
                ->on('movimientos_caja')
                ->onDelete('set null');

            // Índices
            $table->index('cobro_id', 'idx_cp_cobro');
            $table->index('forma_pago_id', 'idx_cp_forma_pago');
            $table->index('estado', 'idx_cp_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('cobro_pagos');
    }
};
