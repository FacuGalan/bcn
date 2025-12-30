<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Formas de Pago
 *
 * Define las formas de pago disponibles (efectivo, tarjeta débito, crédito, transferencia, QR, etc.)
 * Nivel comercio - Se configura disponibilidad por sucursal en tabla pivot.
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
        Schema::connection('pymes_tenant')->create('formas_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre de la forma de pago');
            $table->string('codigo', 50)->nullable()->comment('Código alfanumérico');
            $table->text('descripcion')->nullable()->comment('Descripción');
            $table->enum('concepto', [
                'efectivo',
                'tarjeta_debito',
                'tarjeta_credito',
                'transferencia',
                'wallet',
                'cheque',
                'otro'
            ])->default('otro')->comment('Concepto de la forma de pago');
            $table->boolean('permite_cuotas')->default(false)->comment('Si permite pago en cuotas');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->timestamps();

            // Índices
            $table->index('nombre', 'idx_nombre');
            $table->index('concepto', 'idx_concepto');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('formas_pago');
    }
};
