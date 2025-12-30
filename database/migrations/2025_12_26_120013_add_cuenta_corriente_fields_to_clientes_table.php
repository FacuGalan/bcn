<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de cuenta corriente a clientes
 *
 * Agrega:
 * - tiene_cuenta_corriente: si el cliente puede comprar a crédito
 * - limite_credito: monto máximo de deuda permitido
 * - saldo_deudor_cache: cache del saldo adeudado (calculado)
 * - saldo_a_favor_cache: cache del saldo a favor (crédito)
 * - dias_credito: días de crédito por defecto
 * - tasa_interes_mensual: interés por mora
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('clientes', function (Blueprint $table) {
            // ==================== Cuenta Corriente ====================
            $table->boolean('tiene_cuenta_corriente')->default(false)->after('lista_precio_id')
                ->comment('Si el cliente puede comprar a crédito');

            $table->decimal('limite_credito', 12, 2)->default(0)->after('tiene_cuenta_corriente')
                ->comment('Límite máximo de crédito (0 = sin límite)');

            $table->unsignedInteger('dias_credito')->default(30)->after('limite_credito')
                ->comment('Días de crédito por defecto para nuevas ventas');

            $table->decimal('tasa_interes_mensual', 6, 2)->default(0)->after('dias_credito')
                ->comment('Tasa de interés mensual por mora (%)');

            // ==================== Campos Cache (Calculados) ====================
            $table->decimal('saldo_deudor_cache', 12, 2)->default(0)->after('tasa_interes_mensual')
                ->comment('Cache: suma de ventas.saldo_pendiente_cache del cliente');

            $table->decimal('saldo_a_favor_cache', 12, 2)->default(0)->after('saldo_deudor_cache')
                ->comment('Cache: saldo a favor del cliente (crédito disponible)');

            $table->timestamp('ultimo_movimiento_cc_at')->nullable()->after('saldo_a_favor_cache')
                ->comment('Fecha del último movimiento en cuenta corriente');

            // ==================== Control de Morosidad ====================
            $table->boolean('bloqueado_por_mora')->default(false)->after('ultimo_movimiento_cc_at')
                ->comment('Si está bloqueado por mora');

            $table->unsignedInteger('dias_mora_max')->default(0)->after('bloqueado_por_mora')
                ->comment('Máximos días de mora actual');

            // ==================== Índices ====================
            $table->index('tiene_cuenta_corriente', 'idx_cli_cc');
            $table->index('saldo_deudor_cache', 'idx_cli_saldo_deudor');
            $table->index('bloqueado_por_mora', 'idx_cli_bloqueado_mora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('clientes', function (Blueprint $table) {
            $table->dropIndex('idx_cli_cc');
            $table->dropIndex('idx_cli_saldo_deudor');
            $table->dropIndex('idx_cli_bloqueado_mora');

            $table->dropColumn([
                'tiene_cuenta_corriente',
                'limite_credito',
                'dias_credito',
                'tasa_interes_mensual',
                'saldo_deudor_cache',
                'saldo_a_favor_cache',
                'ultimo_movimiento_cc_at',
                'bloqueado_por_mora',
                'dias_mora_max',
            ]);
        });
    }
};
