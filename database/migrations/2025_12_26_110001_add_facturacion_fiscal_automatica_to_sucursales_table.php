<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campo facturacion_fiscal_automatica a la tabla sucursales
 *
 * Este campo indica si la sucursal emite factura fiscal automáticamente
 * basándose en la configuración de las formas de pago seleccionadas,
 * sin preguntar al usuario.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
            $table->boolean('facturacion_fiscal_automatica')->default(false)->after('agrupa_articulos_impresion')
                ->comment('Si emite factura fiscal automáticamente según formas de pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
            $table->dropColumn('facturacion_fiscal_automatica');
        });
    }
};
