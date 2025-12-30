<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla venta_promociones
 *
 * Registra las promociones aplicadas a nivel de VENTA (no a ítems individuales).
 * Ejemplos: descuento por monto total, promoción por forma de pago, etc.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('venta_promociones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');

            // Tipo de promoción
            $table->enum('tipo_promocion', ['promocion', 'promocion_especial', 'forma_pago', 'cupon'])
                ->comment('Tipo de promoción aplicada');

            // ID de la promoción según el tipo
            $table->unsignedBigInteger('promocion_id')->nullable();
            $table->unsignedBigInteger('promocion_especial_id')->nullable();
            $table->unsignedBigInteger('forma_pago_id')->nullable()
                ->comment('FK para descuentos por forma de pago');

            // Código de cupón si aplica
            $table->string('codigo_cupon', 50)->nullable()
                ->comment('Código del cupón utilizado');

            // Detalle del beneficio
            $table->string('descripcion_promocion', 255)
                ->comment('Descripción de la promoción');

            $table->enum('tipo_beneficio', ['porcentaje', 'monto_fijo'])
                ->comment('Tipo de descuento');

            $table->decimal('valor_beneficio', 12, 2)
                ->comment('Valor del beneficio (% o monto)');

            $table->decimal('descuento_aplicado', 12, 2)
                ->comment('Monto del descuento efectivamente aplicado');

            // Para promociones con monto mínimo
            $table->decimal('monto_minimo_requerido', 12, 2)->nullable()
                ->comment('Monto mínimo que se requería para aplicar');

            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('venta_id', 'fk_vp_venta')
                ->references('id')
                ->on('ventas')
                ->onDelete('cascade');

            $table->foreign('promocion_id', 'fk_vp_promocion')
                ->references('id')
                ->on('promociones')
                ->onDelete('set null');

            $table->foreign('promocion_especial_id', 'fk_vp_promocion_especial')
                ->references('id')
                ->on('promociones_especiales')
                ->onDelete('set null');

            $table->foreign('forma_pago_id', 'fk_vp_forma_pago')
                ->references('id')
                ->on('formas_pago')
                ->onDelete('set null');

            // Índices
            $table->index('venta_id', 'idx_vp_venta');
            $table->index('tipo_promocion', 'idx_vp_tipo');
            $table->index('codigo_cupon', 'idx_vp_cupon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('venta_promociones');
    }
};
