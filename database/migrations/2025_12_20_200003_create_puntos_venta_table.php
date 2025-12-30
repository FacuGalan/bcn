<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de puntos de venta
 *
 * Esta tabla almacena los puntos de venta AFIP asociados a cada CUIT,
 * incluyendo los certificados digitales para facturación electrónica.
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
        Schema::connection($this->connection)->create('puntos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuit_id')->constrained('cuits')->cascadeOnDelete();
            $table->smallInteger('numero')->comment('Número de punto de venta (1-99999)');
            $table->string('nombre', 100)->nullable()->comment('Descripción o alias del punto');
            $table->string('certificado_path', 255)->nullable()->comment('Path al certificado encriptado');
            $table->string('clave_path', 255)->nullable()->comment('Path a la clave privada encriptada');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['cuit_id', 'numero'], 'uk_puntos_venta_cuit_numero');
            $table->index('cuit_id', 'idx_puntos_venta_cuit');
            $table->index('activo', 'idx_puntos_venta_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('puntos_venta');
    }
};
