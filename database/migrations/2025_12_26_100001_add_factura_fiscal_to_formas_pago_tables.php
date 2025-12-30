<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campo factura_fiscal a las tablas de formas de pago
 *
 * Este campo indica si una forma de pago debe generar factura fiscal por defecto.
 * Sigue el mismo patrón que ajuste_porcentaje:
 * - En formas_pago: valor por defecto a nivel empresa (boolean, default false)
 * - En formas_pago_sucursales: valor específico por sucursal (nullable, si es null usa el de empresa)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar a tabla formas_pago (configuración general de empresa)
        Schema::connection('pymes_tenant')->table('formas_pago', function (Blueprint $table) {
            $table->boolean('factura_fiscal')->default(false)->after('ajuste_porcentaje')
                ->comment('Si esta forma de pago genera factura fiscal por defecto');
        });

        // Agregar a tabla formas_pago_sucursales (configuración específica por sucursal)
        Schema::connection('pymes_tenant')->table('formas_pago_sucursales', function (Blueprint $table) {
            $table->boolean('factura_fiscal')->nullable()->after('ajuste_porcentaje')
                ->comment('Factura fiscal específico para esta sucursal (null = usar el de empresa)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago_sucursales', function (Blueprint $table) {
            $table->dropColumn('factura_fiscal');
        });

        Schema::connection('pymes_tenant')->table('formas_pago', function (Blueprint $table) {
            $table->dropColumn('factura_fiscal');
        });
    }
};
