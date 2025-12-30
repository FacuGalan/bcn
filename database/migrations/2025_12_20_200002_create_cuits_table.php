<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de CUITs
 *
 * Esta tabla almacena los CUITs del comercio con sus datos fiscales
 * para facturación electrónica AFIP.
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
        Schema::connection($this->connection)->create('cuits', function (Blueprint $table) {
            $table->id();
            $table->string('numero_cuit', 11)->unique()->comment('CUIT sin guiones, 11 dígitos');
            $table->string('razon_social', 200);
            $table->string('nombre_fantasia', 200)->nullable();
            $table->text('direccion')->nullable();
            $table->unsignedBigInteger('localidad_id')->nullable()->comment('FK a config.localidades');
            $table->unsignedBigInteger('condicion_iva_id')->comment('FK a config.condiciones_iva');
            $table->string('numero_iibb', 50)->nullable()->comment('Número de Ingresos Brutos');
            $table->date('fecha_inicio_actividades')->nullable();
            $table->date('fecha_vencimiento_certificado')->nullable();
            $table->enum('entorno_afip', ['testing', 'produccion'])->default('testing');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('numero_cuit', 'idx_cuits_numero');
            $table->index('activo', 'idx_cuits_activo');
            $table->index('condicion_iva_id', 'idx_cuits_condicion_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cuits');
    }
};
