<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo logo_path a la tabla sucursales
 *
 * Permite que cada sucursal tenga su propio logo para facturas y documentos.
 */
return new class extends Migration
{
    /**
     * La conexión de base de datos a usar
     */
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('sucursales', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('sucursales', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
