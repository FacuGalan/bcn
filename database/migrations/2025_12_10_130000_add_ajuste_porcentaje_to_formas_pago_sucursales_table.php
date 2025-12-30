<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campo de ajuste porcentual a formas_pago_sucursales
 *
 * Permite definir un ajuste (recargo/descuento) específico por sucursal
 * que sobrescribe el ajuste general de la forma de pago.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago_sucursales', function (Blueprint $table) {
            $table->decimal('ajuste_porcentaje', 8, 2)->nullable()
                ->after('activo')
                ->comment('Ajuste porcentual específico para esta sucursal: positivo=recargo, negativo=descuento. NULL = usar el de la forma de pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago_sucursales', function (Blueprint $table) {
            $table->dropColumn('ajuste_porcentaje');
        });
    }
};
