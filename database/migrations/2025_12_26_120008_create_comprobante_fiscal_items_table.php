<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla comprobante_fiscal_items
 *
 * Registra los ítems de cada comprobante fiscal.
 * Permite saber exactamente qué se facturó en cada comprobante.
 *
 * IMPORTANTE:
 * - Un ítem de venta puede estar en múltiples comprobantes (facturación parcial)
 * - Un comprobante puede tener ítems de múltiples ventas
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('comprobante_fiscal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_fiscal_id');

            // Referencia al ítem de venta original (opcional)
            $table->unsignedBigInteger('venta_detalle_id')->nullable()
                ->comment('FK al ítem de venta (si aplica)');

            // Datos del ítem (copiados para inmutabilidad)
            $table->string('codigo', 50)->nullable()
                ->comment('Código del artículo');
            $table->string('descripcion', 500)
                ->comment('Descripción del artículo/servicio');

            $table->decimal('cantidad', 12, 4);
            $table->string('unidad_medida', 10)->default('u')
                ->comment('Código unidad de medida AFIP');

            // Precios
            $table->decimal('precio_unitario', 12, 4)
                ->comment('Precio unitario neto');
            $table->decimal('bonificacion', 12, 2)->default(0)
                ->comment('Descuento/bonificación');
            $table->decimal('subtotal', 12, 2)
                ->comment('Subtotal neto');

            // IVA del ítem
            $table->unsignedTinyInteger('iva_codigo_afip')
                ->comment('Código AFIP de la alícuota');
            $table->decimal('iva_alicuota', 5, 2)
                ->comment('Porcentaje de IVA');
            $table->decimal('iva_importe', 12, 2)
                ->comment('Importe de IVA del ítem');

            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('comprobante_fiscal_id', 'fk_cfitems_comprobante')
                ->references('id')
                ->on('comprobantes_fiscales')
                ->onDelete('cascade');

            $table->foreign('venta_detalle_id', 'fk_cfitems_venta_detalle')
                ->references('id')
                ->on('ventas_detalle')
                ->onDelete('set null');

            // Índices
            $table->index('comprobante_fiscal_id', 'idx_cfitems_comprobante');
            $table->index('venta_detalle_id', 'idx_cfitems_venta_detalle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('comprobante_fiscal_items');
    }
};
