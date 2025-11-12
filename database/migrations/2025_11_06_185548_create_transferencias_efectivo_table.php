<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Transferencias de Efectivo
 *
 * Registra las transferencias de efectivo entre cajas.
 * Puede ser entre cajas de la misma o diferentes sucursales.
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
        Schema::connection('pymes_tenant')->create('transferencias_efectivo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_origen_id');
            $table->unsignedBigInteger('caja_destino_id');
            $table->decimal('monto', 10, 2);
            $table->enum('estado', ['pendiente', 'aprobada', 'recibida', 'rechazada'])->default('pendiente');
            $table->unsignedBigInteger('autorizado_por_user_id');
            $table->unsignedBigInteger('recibido_por_user_id')->nullable();
            $table->timestamp('fecha_autorizacion')->useCurrent();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('caja_origen_id', 'fk_transferencias_efectivo_origen')
                  ->references('id')
                  ->on('cajas')
                  ->onDelete('restrict');

            $table->foreign('caja_destino_id', 'fk_transferencias_efectivo_destino')
                  ->references('id')
                  ->on('cajas')
                  ->onDelete('restrict');

            // Índices
            $table->index(['caja_origen_id', 'caja_destino_id'], 'idx_origen_destino');
            $table->index('estado', 'idx_estado');
            $table->index('fecha_autorizacion', 'idx_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('transferencias_efectivo');
    }
};
