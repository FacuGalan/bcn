<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar nombre público a sucursales
 *
 * - nombre: Nombre interno para uso del sistema (ej: "Sucursal Norte")
 * - nombre_publico: Nombre comercial visible al público (ej: "Helados Favoritos Rivadavia")
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
                $table->string('nombre_publico', 200)->nullable()
                    ->after('nombre')
                    ->comment('Nombre comercial visible al público');
            });
        } catch (\Exception $e) {
            // Column already exists, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
            $table->dropColumn('nombre_publico');
        });
    }
};
