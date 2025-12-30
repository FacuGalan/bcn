<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de promociones a ventas_detalle
 *
 * Agrega campos para registrar los descuentos aplicados por promociones.
 * NOTA: precio_lista ya existe en la tabla.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            // Descuento por promoción automática
            $table->decimal('descuento_promocion', 12, 2)->default(0)->after('descuento_monto')
                ->comment('Descuento aplicado por promociones automáticas');

            // Descuento por lista de precios del cliente
            $table->decimal('descuento_lista', 12, 2)->default(0)->after('descuento_promocion')
                ->comment('Descuento por lista de precios asignada al cliente');

            // Indica si el ítem tiene promoción aplicada
            $table->boolean('tiene_promocion')->default(false)->after('descuento_lista')
                ->comment('Indica si se aplicó alguna promoción');

            // Índice para reportes de promociones
            $table->index('tiene_promocion', 'idx_venta_detalle_promocion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->dropIndex('idx_venta_detalle_promocion');
            $table->dropColumn([
                'descuento_promocion',
                'descuento_lista',
                'tiene_promocion',
            ]);
        });
    }
};
