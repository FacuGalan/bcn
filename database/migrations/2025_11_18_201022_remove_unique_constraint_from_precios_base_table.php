<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Remover constraint único de precios_base
 *
 * Razón: La constraint 'unique_articulo_sucursal_forma_canal' impide tener
 * múltiples precios para el mismo contexto pero con diferentes fechas de vigencia.
 *
 * Ejemplo bloqueado por la constraint:
 * - Precio 1: Delivery+AppDigital del 13/11 al 30/11
 * - Precio 2: Delivery+AppDigital del 01/12 al 15/12 (diferente período!)
 *
 * La validación de solapamiento de fechas ya se maneja en WizardPrecio->verificarConflictos()
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('precios_base', function (Blueprint $table) {
            // Eliminar la constraint única que impide múltiples precios con diferentes fechas
            $table->dropUnique('unique_articulo_sucursal_forma_canal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('precios_base', function (Blueprint $table) {
            // Restaurar la constraint única si se hace rollback
            $table->unique(
                ['articulo_id', 'sucursal_id', 'forma_venta_id', 'canal_venta_id'],
                'unique_articulo_sucursal_forma_canal'
            );
        });
    }
};
