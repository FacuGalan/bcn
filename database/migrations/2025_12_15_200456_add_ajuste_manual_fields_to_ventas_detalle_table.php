<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Campos de ajuste manual en ventas_detalle
 *
 * Agrega campos para registrar ajustes manuales de precio
 * realizados directamente en el POS.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            // Tipo de ajuste manual: 'monto' (precio fijo) o 'porcentaje' (descuento/recargo %)
            $table->string('ajuste_manual_tipo', 20)->nullable()->after('subtotal');
            // Valor del ajuste (precio en caso de 'monto', porcentaje en caso de 'porcentaje')
            $table->decimal('ajuste_manual_valor', 12, 2)->nullable()->after('ajuste_manual_tipo');
            // Precio antes del ajuste manual (para referencia)
            $table->decimal('precio_sin_ajuste_manual', 12, 2)->nullable()->after('ajuste_manual_valor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->dropColumn([
                'ajuste_manual_tipo',
                'ajuste_manual_valor',
                'precio_sin_ajuste_manual',
            ]);
        });
    }
};
