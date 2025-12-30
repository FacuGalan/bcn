<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campo descripcion a formas_pago_cuotas
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::connection('pymes_tenant')->table('formas_pago_cuotas', function (Blueprint $table) {
                $table->string('descripcion', 200)->nullable()
                    ->after('recargo_porcentaje')
                    ->comment('Descripción opcional del plan de cuotas');
            });
        } catch (\Exception $e) {
            // Table doesn't exist or column already exists, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('formas_pago_cuotas', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
