<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campos de configuración a la tabla cajas
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
        Schema::connection($this->connection)->table('cajas', function (Blueprint $table) {
            $table->decimal('limite_efectivo', 12, 2)->nullable()->after('activo')->comment('Límite máximo de efectivo en caja');
            $table->enum('modo_carga_inicial', ['manual', 'ultimo_cierre', 'monto_fijo'])->default('manual')->after('limite_efectivo')->comment('Forma de carga inicial de cada turno');
            $table->decimal('monto_fijo_inicial', 12, 2)->nullable()->after('modo_carga_inicial')->comment('Monto fijo para carga inicial (si modo es monto_fijo)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('cajas', function (Blueprint $table) {
            $table->dropColumn(['limite_efectivo', 'modo_carga_inicial', 'monto_fijo_inicial']);
        });
    }
};
