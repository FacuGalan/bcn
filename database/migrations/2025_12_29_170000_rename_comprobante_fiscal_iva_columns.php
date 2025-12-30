<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Renombra columnas en comprobante_fiscal_iva para mayor claridad:
 * - base_imponible → neto
 * - importe → iva
 * - Agrega: total (neto + iva)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('comprobante_fiscal_iva', function (Blueprint $table) {
            $table->renameColumn('base_imponible', 'neto');
            $table->renameColumn('importe', 'iva');
        });

        Schema::connection('pymes_tenant')->table('comprobante_fiscal_iva', function (Blueprint $table) {
            $table->decimal('total', 12, 2)->after('iva')->default(0);
        });

        // Actualizar total en registros existentes
        DB::connection('pymes_tenant')->statement('
            UPDATE comprobante_fiscal_iva
            SET total = neto + iva
        ');
    }

    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('comprobante_fiscal_iva', function (Blueprint $table) {
            $table->dropColumn('total');
        });

        Schema::connection('pymes_tenant')->table('comprobante_fiscal_iva', function (Blueprint $table) {
            $table->renameColumn('neto', 'base_imponible');
            $table->renameColumn('iva', 'importe');
        });
    }
};
