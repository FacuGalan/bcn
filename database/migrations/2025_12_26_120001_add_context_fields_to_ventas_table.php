<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de anulación y soft deletes a ventas
 *
 * NOTA: Los campos de contexto, IVA y cuenta corriente ya existen en la tabla.
 * Esta migración solo agrega:
 * - Campos de anulación (anulado_por, anulado_at, motivo)
 * - Soft deletes
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            // ==================== Anulación ====================
            $table->unsignedBigInteger('anulado_por_usuario_id')->nullable()->after('monto_no_fiscal_cache')
                ->comment('Usuario que anuló la venta');

            $table->timestamp('anulado_at')->nullable()->after('anulado_por_usuario_id')
                ->comment('Fecha/hora de anulación');

            $table->string('motivo_anulacion', 500)->nullable()->after('anulado_at')
                ->comment('Motivo de la anulación');

            // ==================== Soft Deletes ====================
            $table->softDeletes();

            // ==================== Índice ====================
            $table->index('deleted_at', 'idx_ventas_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_deleted');

            $table->dropColumn([
                'anulado_por_usuario_id',
                'anulado_at',
                'motivo_anulacion',
                'deleted_at',
            ]);
        });
    }
};
