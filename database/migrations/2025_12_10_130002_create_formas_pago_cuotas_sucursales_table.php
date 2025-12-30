<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla pivot para configurar cuotas por sucursal
 *
 * Permite definir un recargo diferente para cada plan de cuotas por sucursal.
 * Si no existe registro, se usa el recargo del plan de cuotas general.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('formas_pago_cuotas_sucursales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forma_pago_cuota_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->decimal('recargo_porcentaje', 5, 2)->nullable()
                ->comment('Recargo específico para esta sucursal. NULL = usar el del plan general');
            $table->boolean('activo')->default(true)
                ->comment('Si este plan de cuotas está activo en esta sucursal');
            $table->timestamps();

            // Clave única
            $table->unique(['forma_pago_cuota_id', 'sucursal_id'], 'unique_cuota_sucursal');

            // Foreign keys
            $table->foreign('forma_pago_cuota_id', 'fk_cuotas_sucursales_cuota')
                  ->references('id')
                  ->on('formas_pago_cuotas')
                  ->onDelete('cascade');

            $table->foreign('sucursal_id', 'fk_cuotas_sucursales_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index(['sucursal_id', 'activo'], 'idx_cuota_sucursal_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('formas_pago_cuotas_sucursales');
    }
};
