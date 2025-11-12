<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar descuento a Precios
 *
 * Permite configurar descuentos por tipo de precio y sucursal.
 *
 * FASE 1 - Sistema Multi-Sucursal (Extensión Descuentos)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('precios', function (Blueprint $table) {
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->after('precio')
                  ->comment('Porcentaje de descuento aplicable (0-100)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('precios', function (Blueprint $table) {
            $table->dropColumn('descuento_porcentaje');
        });
    }
};
