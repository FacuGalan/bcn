<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Agregar ajuste_forma_pago a ventas
 *
 * Este campo guarda la suma de los ajustes (recargos/descuentos) de las formas de pago.
 * Permite la fórmula: subtotal - descuento + ajuste_forma_pago = total_final
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            $table->decimal('ajuste_forma_pago', 12, 2)->default(0)->after('total')
                ->comment('Suma de ajustes (recargos/descuentos) de formas de pago. total + ajuste = total_final');
        });

        // Actualizar ventas existentes calculando el ajuste desde venta_pagos
        $this->actualizarVentasExistentes();
    }

    /**
     * Actualiza las ventas existentes calculando el ajuste desde venta_pagos
     */
    protected function actualizarVentasExistentes(): void
    {
        $conn = DB::connection('pymes_tenant');

        // Obtener el prefijo del tenant desde la tabla prefix de la migración
        $prefix = $conn->getTablePrefix();

        // Calcular ajuste para cada venta desde sus pagos
        $conn->statement("
            UPDATE {$prefix}ventas v
            SET ajuste_forma_pago = COALESCE(
                (SELECT SUM(monto_ajuste) FROM {$prefix}venta_pagos WHERE venta_id = v.id),
                0
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            $table->dropColumn('ajuste_forma_pago');
        });
    }
};
