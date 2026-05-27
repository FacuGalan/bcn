<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.5 (Integraciones de Pago): rubro del comercio.
 *
 * Necesario para enviar `category` (MCC) correcto al crear POS en MP.
 * Mercado Pago solo acepta categorías para gastronomía y estación de
 * servicio; el resto se omite. Valores: gastronomia, estacion_servicio, otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->table('comercios', function (Blueprint $table) {
            $table->string('rubro', 50)->nullable()->after('max_usuarios');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('comercios', function (Blueprint $table) {
            $table->dropColumn('rubro');
        });
    }
};
