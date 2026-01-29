<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega cierre_turno_id a ventas, venta_pagos, cobros y cobro_pagos
 * para permitir trazabilidad completa de qué transacciones participaron
 * en cada cierre de turno.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        // Agregar cierre_turno_id a ventas
        Schema::connection($this->connection)->table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('cierre_turno_id')->nullable()->after('observaciones')
                ->comment('Cierre de turno donde se registró la venta');
            $table->index('cierre_turno_id', 'idx_ventas_cierre_turno');
        });

        // Agregar cierre_turno_id a venta_pagos
        Schema::connection($this->connection)->table('venta_pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('cierre_turno_id')->nullable()->after('motivo_anulacion')
                ->comment('Cierre de turno donde se procesó este pago');
            $table->index('cierre_turno_id', 'idx_venta_pagos_cierre_turno');
        });

        // Agregar cierre_turno_id a cobros
        Schema::connection($this->connection)->table('cobros', function (Blueprint $table) {
            $table->unsignedBigInteger('cierre_turno_id')->nullable()->after('motivo_anulacion')
                ->comment('Cierre de turno donde se registró el cobro');
            $table->index('cierre_turno_id', 'idx_cobros_cierre_turno');
        });

        // Agregar cierre_turno_id a cobro_pagos
        Schema::connection($this->connection)->table('cobro_pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('cierre_turno_id')->nullable()->after('estado')
                ->comment('Cierre de turno donde se procesó este pago');
            $table->index('cierre_turno_id', 'idx_cobro_pagos_cierre_turno');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_cierre_turno');
            $table->dropColumn('cierre_turno_id');
        });

        Schema::connection($this->connection)->table('venta_pagos', function (Blueprint $table) {
            $table->dropIndex('idx_venta_pagos_cierre_turno');
            $table->dropColumn('cierre_turno_id');
        });

        Schema::connection($this->connection)->table('cobros', function (Blueprint $table) {
            $table->dropIndex('idx_cobros_cierre_turno');
            $table->dropColumn('cierre_turno_id');
        });

        Schema::connection($this->connection)->table('cobro_pagos', function (Blueprint $table) {
            $table->dropIndex('idx_cobro_pagos_cierre_turno');
            $table->dropColumn('cierre_turno_id');
        });
    }
};
