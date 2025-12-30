<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de cuenta corriente a venta_pagos
 *
 * Agrega campos para manejar:
 * - Pagos que van a cuenta corriente vs pagos al contado
 * - Pagos que afectan caja vs pagos que no
 * - Estado del pago (para anulaciones)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('venta_pagos', function (Blueprint $table) {
            // Si el pago es "a cuenta corriente" (genera deuda)
            $table->boolean('es_cuenta_corriente')->default(false)->after('observaciones')
                ->comment('True si este pago genera deuda en cuenta corriente');

            // Si el pago afecta la caja (genera movimiento de caja)
            $table->boolean('afecta_caja')->default(true)->after('es_cuenta_corriente')
                ->comment('True si genera movimiento en caja');

            // Estado del pago
            $table->enum('estado', ['activo', 'anulado'])->default('activo')->after('afecta_caja')
                ->comment('Estado del pago');

            // Relación con movimiento de caja (si afecta_caja = true)
            $table->unsignedBigInteger('movimiento_caja_id')->nullable()->after('estado')
                ->comment('FK al movimiento de caja generado');

            // Para anulaciones
            $table->unsignedBigInteger('anulado_por_usuario_id')->nullable()->after('movimiento_caja_id');
            $table->timestamp('anulado_at')->nullable()->after('anulado_por_usuario_id');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulado_at');

            // Foreign key
            $table->foreign('movimiento_caja_id', 'fk_venta_pagos_mov_caja')
                ->references('id')
                ->on('movimientos_caja')
                ->onDelete('set null');

            // Índices
            $table->index('es_cuenta_corriente', 'idx_vp_cuenta_corriente');
            $table->index('estado', 'idx_vp_estado');
            $table->index('afecta_caja', 'idx_vp_afecta_caja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('venta_pagos', function (Blueprint $table) {
            $table->dropForeign('fk_venta_pagos_mov_caja');
            $table->dropIndex('idx_vp_cuenta_corriente');
            $table->dropIndex('idx_vp_estado');
            $table->dropIndex('idx_vp_afecta_caja');

            $table->dropColumn([
                'es_cuenta_corriente',
                'afecta_caja',
                'estado',
                'movimiento_caja_id',
                'anulado_por_usuario_id',
                'anulado_at',
                'motivo_anulacion',
            ]);
        });
    }
};
