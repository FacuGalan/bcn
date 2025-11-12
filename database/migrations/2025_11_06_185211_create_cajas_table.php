<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Cajas
 *
 * Gestiona las cajas de cada sucursal.
 * Cada sucursal puede tener múltiples cajas (efectivo, banco, tarjeta, etc.).
 *
 * FASE 1 - Sistema Multi-Sucursal
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->string('nombre', 100)->comment('Nombre de la caja');
            $table->enum('tipo', ['efectivo', 'banco', 'tarjeta', 'cheque', 'otro'])->default('efectivo');
            $table->decimal('saldo_inicial', 10, 2)->default(0)->comment('Saldo al iniciar');
            $table->decimal('saldo_actual', 10, 2)->default(0)->comment('Saldo actual');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Foreign key
            $table->foreign('sucursal_id', 'fk_cajas_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index('sucursal_id', 'idx_sucursal');
            $table->index('tipo', 'idx_tipo');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('cajas');
    }
};
