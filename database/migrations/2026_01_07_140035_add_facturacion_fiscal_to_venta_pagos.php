<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('venta_pagos', function (Blueprint $table) {
            // ID del comprobante fiscal asociado a este pago (para saber qué factura cubre este pago)
            $table->unsignedBigInteger('comprobante_fiscal_id')->nullable()->after('movimiento_caja_id');
            // Monto de este pago que fue facturado fiscalmente (puede ser parcial)
            $table->decimal('monto_facturado', 12, 2)->nullable()->after('comprobante_fiscal_id');

            $table->foreign('comprobante_fiscal_id')
                ->references('id')
                ->on('comprobantes_fiscales')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('venta_pagos', function (Blueprint $table) {
            $table->dropForeign(['comprobante_fiscal_id']);
            $table->dropColumn(['comprobante_fiscal_id', 'monto_facturado']);
        });
    }
};
