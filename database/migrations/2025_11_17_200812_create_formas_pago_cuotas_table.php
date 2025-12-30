<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Formas de Pago - Cuotas
 *
 * Configuración de cuotas y recargos para formas de pago que lo permiten.
 * Permite configurar diferentes planes de cuotas por sucursal.
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('formas_pago_cuotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forma_pago_id');
            $table->unsignedBigInteger('sucursal_id')->nullable()->comment('NULL = aplica a todas las sucursales');
            $table->integer('cantidad_cuotas')->comment('Cantidad de cuotas (1, 3, 6, 12, etc.)');
            $table->decimal('recargo_porcentaje', 5, 2)->default(0)->comment('Recargo porcentual (0 = sin interés)');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->timestamps();

            // Foreign keys
            $table->foreign('forma_pago_id', 'fk_formas_pago_cuotas_forma')
                  ->references('id')
                  ->on('formas_pago')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_formas_pago_cuotas_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index(['forma_pago_id', 'sucursal_id', 'activo'], 'idx_forma_sucursal_activo');
            $table->index('cantidad_cuotas', 'idx_cantidad_cuotas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('formas_pago_cuotas');
    }
};
