<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de condiciones de IVA
 *
 * Esta tabla almacena las 14 condiciones de IVA definidas por AFIP.
 * Es una tabla de referencia compartida en la base de datos config.
 */
return new class extends Migration
{
    /**
     * La conexión de base de datos a usar
     */
    protected $connection = 'config';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::connection($this->connection)->create('condiciones_iva', function (Blueprint $table) {
                $table->id();
                $table->smallInteger('codigo')->unique()->comment('Código AFIP (1-14)');
                $table->string('nombre', 100);
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();

                $table->index('codigo', 'idx_condiciones_iva_codigo');
            });
        } catch (\Exception $e) {
            // Table already exists, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('condiciones_iva');
    }
};
