<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla cobros
 *
 * Registra los cobros realizados para saldar cuentas corrientes.
 * Un cobro puede aplicarse a múltiples ventas (vía cobro_ventas).
 *
 * DIFERENCIA con venta_pagos:
 * - venta_pagos: pagos al momento de la venta
 * - cobros: pagos posteriores para saldar cuenta corriente
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('cobros', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('caja_id')->nullable()
                ->comment('Caja donde se registró el cobro');

            // Número de recibo
            $table->string('numero_recibo', 50)
                ->comment('Número de recibo de cobro');

            $table->date('fecha');
            $table->time('hora')->nullable();

            // Montos
            $table->decimal('monto_cobrado', 12, 2)
                ->comment('Monto total cobrado');

            $table->decimal('interes_aplicado', 12, 2)->default(0)
                ->comment('Interés cobrado (calculado al momento del cobro)');

            $table->decimal('descuento_aplicado', 12, 2)->default(0)
                ->comment('Descuento por pronto pago u otro');

            $table->decimal('monto_aplicado_a_deuda', 12, 2)
                ->comment('Monto que se aplicó a cancelar deuda');

            $table->decimal('monto_a_favor', 12, 2)->default(0)
                ->comment('Monto que quedó a favor del cliente');

            // Estado
            $table->enum('estado', ['activo', 'anulado'])->default('activo');

            // Observaciones
            $table->text('observaciones')->nullable();

            // Auditoría
            $table->unsignedBigInteger('usuario_id')
                ->comment('Usuario que registró el cobro');

            // Anulación
            $table->unsignedBigInteger('anulado_por_usuario_id')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_cobros_sucursal')
                ->references('id')
                ->on('sucursales')
                ->onDelete('restrict');

            $table->foreign('cliente_id', 'fk_cobros_cliente')
                ->references('id')
                ->on('clientes')
                ->onDelete('restrict');

            $table->foreign('caja_id', 'fk_cobros_caja')
                ->references('id')
                ->on('cajas')
                ->onDelete('set null');

            // Índices
            $table->index(['sucursal_id', 'fecha'], 'idx_cobros_sucursal_fecha');
            $table->index('cliente_id', 'idx_cobros_cliente');
            $table->index('estado', 'idx_cobros_estado');
            $table->index('fecha', 'idx_cobros_fecha');
            $table->unique(['sucursal_id', 'numero_recibo'], 'unique_cobros_recibo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('cobros');
    }
};
