<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Pivot Impresora-Sucursal-Caja
 *
 * Asigna impresoras a sucursales y opcionalmente a cajas específicas.
 * Si caja_id es null, la impresora aplica a toda la sucursal.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('impresora_sucursal_caja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('impresora_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('caja_id')->nullable()->comment('null = aplica a toda la sucursal');
            $table->boolean('es_defecto')->default(false)->comment('Si es la impresora por defecto');
            $table->timestamps();

            $table->unique(['impresora_id', 'sucursal_id', 'caja_id'], 'uk_impresora_sucursal_caja');
            $table->index('impresora_id', 'idx_isc_impresora');
            $table->index('sucursal_id', 'idx_isc_sucursal');
            $table->index('caja_id', 'idx_isc_caja');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('impresora_sucursal_caja');
    }
};
