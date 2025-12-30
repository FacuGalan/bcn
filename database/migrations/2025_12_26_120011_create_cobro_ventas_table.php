<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla cobro_ventas
 *
 * Tabla pivot que relaciona cobros con ventas.
 * Permite que un cobro se aplique a múltiples ventas y
 * que una venta sea saldada con múltiples cobros.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('cobro_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('venta_id');

            // Monto aplicado a esta venta específica
            $table->decimal('monto_aplicado', 12, 2)
                ->comment('Monto del cobro aplicado a esta venta');

            // Interés aplicado a esta venta específica
            $table->decimal('interes_aplicado', 12, 2)->default(0)
                ->comment('Interés cobrado por esta venta');

            // Saldo de la venta ANTES de este cobro (para auditoría)
            $table->decimal('saldo_anterior', 12, 2)
                ->comment('Saldo pendiente de la venta antes del cobro');

            // Saldo de la venta DESPUÉS de este cobro
            $table->decimal('saldo_posterior', 12, 2)
                ->comment('Saldo pendiente de la venta después del cobro');

            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('cobro_id', 'fk_cv_cobro')
                ->references('id')
                ->on('cobros')
                ->onDelete('cascade');

            $table->foreign('venta_id', 'fk_cv_venta')
                ->references('id')
                ->on('ventas')
                ->onDelete('restrict');

            // Índices
            $table->index('cobro_id', 'idx_cv_cobro');
            $table->index('venta_id', 'idx_cv_venta');

            // Una venta solo puede aparecer una vez por cobro
            $table->unique(['cobro_id', 'venta_id'], 'unique_cv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('cobro_ventas');
    }
};
