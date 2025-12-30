<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla venta_detalle_promociones
 *
 * Registra qué promociones se aplicaron a cada ítem de la venta.
 * Permite trazabilidad completa de los descuentos aplicados.
 *
 * Un ítem puede tener múltiples promociones (acumulables)
 * o una sola promoción (excluyente).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('venta_detalle_promociones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_detalle_id');

            // Tipo de promoción (para saber qué tabla consultar)
            $table->enum('tipo_promocion', ['promocion', 'promocion_especial', 'lista_precio'])
                ->comment('Tipo de promoción aplicada');

            // ID de la promoción según el tipo
            $table->unsignedBigInteger('promocion_id')->nullable()
                ->comment('FK a promociones (tipo=promocion)');

            $table->unsignedBigInteger('promocion_especial_id')->nullable()
                ->comment('FK a promociones_especiales (tipo=promocion_especial)');

            $table->unsignedBigInteger('lista_precio_id')->nullable()
                ->comment('FK a listas_precios (tipo=lista_precio)');

            // Detalle del beneficio aplicado
            $table->string('descripcion_promocion', 255)
                ->comment('Nombre/descripción de la promoción al momento de la venta');

            $table->enum('tipo_beneficio', ['porcentaje', 'monto_fijo', 'precio_especial', 'nx1'])
                ->comment('Tipo de beneficio aplicado');

            $table->decimal('valor_beneficio', 12, 2)
                ->comment('Valor del beneficio (%, monto o precio)');

            $table->decimal('descuento_aplicado', 12, 2)
                ->comment('Monto del descuento efectivamente aplicado');

            // Para promociones NxM
            $table->unsignedInteger('cantidad_requerida')->nullable()
                ->comment('N en promoción NxM');

            $table->unsignedInteger('cantidad_bonificada')->nullable()
                ->comment('M unidades gratis en NxM');

            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('venta_detalle_id', 'fk_vdp_venta_detalle')
                ->references('id')
                ->on('ventas_detalle')
                ->onDelete('cascade');

            $table->foreign('promocion_id', 'fk_vdp_promocion')
                ->references('id')
                ->on('promociones')
                ->onDelete('set null');

            $table->foreign('promocion_especial_id', 'fk_vdp_promocion_especial')
                ->references('id')
                ->on('promociones_especiales')
                ->onDelete('set null');

            $table->foreign('lista_precio_id', 'fk_vdp_lista_precio')
                ->references('id')
                ->on('listas_precios')
                ->onDelete('set null');

            // Índices
            $table->index('venta_detalle_id', 'idx_vdp_venta_detalle');
            $table->index('tipo_promocion', 'idx_vdp_tipo');
            $table->index('promocion_id', 'idx_vdp_promocion');
            $table->index('promocion_especial_id', 'idx_vdp_promo_especial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('venta_detalle_promociones');
    }
};
