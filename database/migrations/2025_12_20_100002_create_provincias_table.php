<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de provincias argentinas
 *
 * Esta tabla almacena las 24 provincias de Argentina con códigos ISO 3166-2:AR.
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
            Schema::connection($this->connection)->create('provincias', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique()->comment('Código ISO 3166-2:AR (ej: AR-C, AR-B)');
                $table->string('nombre', 100);
                $table->timestamps();

                $table->index('nombre', 'idx_provincias_nombre');
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
        Schema::connection($this->connection)->dropIfExists('provincias');
    }
};
