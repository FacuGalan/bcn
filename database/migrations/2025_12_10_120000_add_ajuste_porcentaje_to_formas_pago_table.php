<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campo de ajuste porcentual a formas de pago
 *
 * Un solo campo que puede ser positivo (recargo) o negativo (descuento)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago', function (Blueprint $table) {
            $table->decimal('ajuste_porcentaje', 8, 2)->default(0)
                ->after('permite_cuotas')
                ->comment('Ajuste porcentual: positivo=recargo, negativo=descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago', function (Blueprint $table) {
            $table->dropColumn('ajuste_porcentaje');
        });
    }
};
