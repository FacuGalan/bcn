<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Refactorizar ventas
 *
 * 1. Cambiar forma_pago (ENUM) por forma_pago_id (FK a formas_pago)
 * 2. Eliminar campos de desglose de IVA (están en comprobantes_fiscales)
 *    - neto_gravado, neto_no_gravado, neto_exento
 *    - iva_105, iva_21, iva_27, percepciones
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            // Agregar forma_pago_id como FK
            $table->unsignedBigInteger('forma_pago_id')->nullable()->after('total_final')
                ->comment('FK a formas_pago - forma de pago principal (para mixtas el detalle está en venta_pagos)');

            $table->foreign('forma_pago_id', 'fk_ventas_forma_pago')
                ->references('id')
                ->on('formas_pago')
                ->onDelete('set null');

            $table->index('forma_pago_id', 'idx_ventas_forma_pago');
        });

        // Migrar datos existentes: mapear valores del ENUM a IDs de formas_pago
        $this->migrarDatosFormaPago();

        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            // Eliminar el campo ENUM forma_pago
            $table->dropColumn('forma_pago');

            // Eliminar campos de desglose de IVA (están en comprobantes_fiscales)
            $table->dropColumn([
                'neto_gravado',
                'neto_no_gravado',
                'neto_exento',
                'iva_105',
                'iva_21',
                'iva_27',
                'percepciones',
            ]);
        });
    }

    /**
     * Migrar datos del ENUM a IDs
     */
    protected function migrarDatosFormaPago(): void
    {
        $conn = DB::connection('pymes_tenant');

        // Obtener mapeo de códigos a IDs
        $formasPago = $conn->table('formas_pago')->pluck('id', 'codigo')->toArray();

        // Mapeo de valores ENUM a códigos de formas_pago
        $mapeo = [
            'efectivo' => 'EFECTIVO',
            'tarjeta' => 'TARJETA_CREDITO', // O TARJETA_DEBITO según el caso
            'transferencia' => 'TRANSFERENCIA',
            'cheque' => 'CHEQUE',
        ];

        foreach ($mapeo as $enumValue => $codigo) {
            if (isset($formasPago[$codigo])) {
                $conn->table('ventas')
                    ->where('forma_pago', $enumValue)
                    ->update(['forma_pago_id' => $formasPago[$codigo]]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            // Restaurar campos de IVA
            $table->decimal('neto_gravado', 12, 2)->default(0)->after('subtotal');
            $table->decimal('neto_no_gravado', 12, 2)->default(0)->after('neto_gravado');
            $table->decimal('neto_exento', 12, 2)->default(0)->after('neto_no_gravado');
            $table->decimal('iva_105', 12, 2)->default(0)->after('neto_exento');
            $table->decimal('iva_21', 12, 2)->default(0)->after('iva_105');
            $table->decimal('iva_27', 12, 2)->default(0)->after('iva_21');
            $table->decimal('percepciones', 12, 2)->default(0)->after('iva_27');

            // Restaurar ENUM forma_pago
            $table->enum('forma_pago', ['efectivo', 'tarjeta', 'transferencia', 'cheque'])
                ->after('total_final');
        });

        Schema::connection('pymes_tenant')->table('ventas', function (Blueprint $table) {
            // Eliminar FK y campo forma_pago_id
            $table->dropForeign('fk_ventas_forma_pago');
            $table->dropIndex('idx_ventas_forma_pago');
            $table->dropColumn('forma_pago_id');
        });
    }
};
