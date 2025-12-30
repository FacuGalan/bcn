<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla pivot CUIT-Sucursal
 *
 * Esta tabla permite asignar CUITs a sucursales específicas.
 * Un CUIT puede estar asociado a múltiples sucursales y viceversa.
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
        Schema::connection($this->connection)->create('cuit_sucursal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuit_id')->constrained('cuits')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->boolean('es_principal')->default(false)->comment('Si es el CUIT principal de la sucursal');
            $table->timestamps();

            $table->unique(['cuit_id', 'sucursal_id'], 'uk_cuit_sucursal');
            $table->index('sucursal_id', 'idx_cuit_sucursal_sucursal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cuit_sucursal');
    }
};
