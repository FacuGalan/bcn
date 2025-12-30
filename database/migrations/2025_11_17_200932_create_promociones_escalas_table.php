<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Promociones - Escalas
 *
 * Define los escalones de descuento por cantidad.
 * Permite configurar descuentos progresivos según cantidad comprada.
 *
 * EJEMPLOS DE USO:
 *
 * 1. Descuento escalonado por porcentaje:
 *    - 1 a 5 unidades: 0% descuento
 *    - 6 a 10 unidades: 10% descuento
 *    - 11+ unidades: 20% descuento
 *
 * 2. Precio fijo por escalón:
 *    - 1 a 5: $100 c/u
 *    - 6 a 10: $90 c/u
 *    - 11+: $80 c/u
 *
 * 3. Regalo por cantidad (100% descuento):
 *    - Cada 4 unidades, 1 gratis (20% = cada 5 pagas 4)
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('promociones_escalas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_id');
            $table->decimal('cantidad_desde', 12, 3)->comment('Cantidad inicial del rango');
            $table->decimal('cantidad_hasta', 12, 3)->nullable()->comment('Cantidad final (NULL = infinito)');

            $table->enum('tipo_descuento', [
                'porcentaje',
                'monto',
                'precio_fijo'
            ])->comment('Tipo de descuento en este escalón');

            $table->decimal('valor', 12, 2)->comment('Valor según tipo (%, monto o precio)');
            $table->timestamps();

            // Foreign keys
            $table->foreign('promocion_id', 'fk_promo_escalas_promocion')
                  ->references('id')
                  ->on('promociones')
                  ->onDelete('cascade');

            // Índices
            $table->index(['promocion_id', 'cantidad_desde'], 'idx_promocion_cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('promociones_escalas');
    }
};
