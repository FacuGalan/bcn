<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de IVA a Ventas Detalle
 *
 * Registra el IVA aplicado en cada ítem de la venta.
 * Importante para facturación electrónica y reportes de IVA.
 *
 * FASE 1 - Sistema Multi-Sucursal (Extensión IVA)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_iva_id')->after('articulo_id')
                  ->comment('Tipo de IVA aplicado en esta venta');
            $table->decimal('iva_porcentaje', 5, 2)->after('tipo_iva_id')
                  ->comment('Porcentaje de IVA al momento de la venta');
            $table->decimal('precio_sin_iva', 10, 2)->after('precio_unitario')
                  ->comment('Precio unitario sin IVA');
            $table->decimal('iva_monto', 10, 2)->default(0)->after('descuento')
                  ->comment('Monto de IVA total del ítem');

            // Foreign key
            $table->foreign('tipo_iva_id', 'fk_ventas_detalle_tipo_iva')
                  ->references('id')
                  ->on('tipos_iva')
                  ->onDelete('restrict');

            // Índice
            $table->index('tipo_iva_id', 'idx_tipo_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->dropForeign('fk_ventas_detalle_tipo_iva');
            $table->dropIndex('idx_tipo_iva');
            $table->dropColumn(['tipo_iva_id', 'iva_porcentaje', 'precio_sin_iva', 'iva_monto']);
        });
    }
};
