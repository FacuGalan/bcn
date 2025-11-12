<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Movimientos de Caja
 *
 * Registra todos los movimientos de dinero en las cajas.
 * Cada movimiento afecta el saldo de una caja.
 *
 * FASE 1 - Sistema Multi-Sucursal
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_id');
            $table->enum('tipo_movimiento', ['venta', 'cobranza', 'gasto', 'transferencia_entrada', 'transferencia_salida', 'ajuste']);
            $table->string('referencia_tipo', 50)->nullable()->comment('Tipo de documento (venta, compra, transferencia, etc.)');
            $table->unsignedBigInteger('referencia_id')->nullable()->comment('ID del documento relacionado');
            $table->decimal('monto', 10, 2)->comment('Monto del movimiento (+ o -)');
            $table->decimal('saldo_anterior', 10, 2)->comment('Saldo antes del movimiento');
            $table->decimal('saldo_nuevo', 10, 2)->comment('Saldo después del movimiento');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('user_id')->comment('Usuario que realizó el movimiento');
            $table->timestamp('created_at')->useCurrent();

            // Foreign key
            $table->foreign('caja_id', 'fk_movimientos_caja_caja')
                  ->references('id')
                  ->on('cajas')
                  ->onDelete('cascade');

            // Índices
            $table->index(['caja_id', 'created_at'], 'idx_caja_fecha');
            $table->index('tipo_movimiento', 'idx_tipo_movimiento');
            $table->index(['referencia_tipo', 'referencia_id'], 'idx_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('movimientos_caja');
    }
};
