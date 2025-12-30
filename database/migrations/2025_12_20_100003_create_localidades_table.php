<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de localidades argentinas
 *
 * Esta tabla almacena las localidades del padrón AFIP (~4000 registros).
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
            Schema::connection($this->connection)->create('localidades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provincia_id')->constrained('provincias')->cascadeOnDelete();
                $table->string('codigo_postal', 10)->nullable();
                $table->string('nombre', 150);
                $table->timestamps();

                $table->index('provincia_id', 'idx_localidades_provincia');
                $table->index('codigo_postal', 'idx_localidades_cp');
                $table->index('nombre', 'idx_localidades_nombre');
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
        Schema::connection($this->connection)->dropIfExists('localidades');
    }
};
