<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla pivot Punto de Venta-Caja
 *
 * Esta tabla permite asignar puntos de venta a cajas específicas.
 * Un punto de venta puede estar asociado a múltiples cajas.
 * La funcionalidad de asignación se implementará posteriormente.
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
        Schema::connection($this->connection)->create('punto_venta_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->boolean('es_defecto')->default(false)->comment('Si es el punto de venta por defecto de la caja');
            $table->timestamps();

            $table->unique(['punto_venta_id', 'caja_id'], 'uk_punto_venta_caja');
            $table->index('caja_id', 'idx_punto_venta_caja_caja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('punto_venta_caja');
    }
};
