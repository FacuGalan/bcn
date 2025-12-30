<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Promociones
 *
 * Sistema flexible de promociones y descuentos.
 * Cada promoción es específica de una sucursal y puede tener múltiples condiciones.
 *
 * TIPOS DE PROMOCIÓN:
 * - descuento_porcentaje: Descuento porcentual (ej: 20% OFF)
 * - descuento_monto: Descuento en monto fijo (ej: $500 OFF)
 * - precio_fijo: Precio fijo (ej: $1000)
 * - recargo_porcentaje: Recargo porcentual (ej: +10%)
 * - recargo_monto: Recargo en monto fijo (ej: +$200)
 * - descuento_escalonado: Usa tabla promociones_escalas
 *
 * VALIDACIONES:
 * - Descuentos finales: máximo 70%
 * - Descuentos por cantidad: pueden ser 100% (ej: cada 4, regalo 1)
 *
 * COMBINABILIDAD:
 * - Si combinable = true → puede combinarse con otras promociones
 * - Si combinable = false → es excluyente
 * - Prioridad define orden de aplicación (1 = mayor prioridad)
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
        Schema::connection('pymes_tenant')->create('promociones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id')->comment('Sucursal a la que aplica');
            $table->string('nombre', 255)->comment('Nombre de la promoción');
            $table->text('descripcion')->nullable()->comment('Descripción detallada');
            $table->string('codigo_cupon', 50)->nullable()->unique()->comment('Código de cupón (si requiere)');

            $table->enum('tipo', [
                'descuento_porcentaje',
                'descuento_monto',
                'precio_fijo',
                'recargo_porcentaje',
                'recargo_monto',
                'descuento_escalonado'
            ])->comment('Tipo de promoción');

            $table->decimal('valor', 12, 2)->default(0)->comment('Valor según tipo (monto o porcentaje)');
            $table->integer('prioridad')->default(999)->comment('Orden de aplicación (1 = mayor prioridad)');
            $table->boolean('combinable')->default(false)->comment('Si puede combinarse con otras');
            $table->boolean('activo')->default(true)->comment('Si está activa');

            // Vigencias
            $table->date('vigencia_desde')->nullable()->comment('Fecha desde la cual aplica');
            $table->date('vigencia_hasta')->nullable()->comment('Fecha hasta la cual aplica');
            $table->text('dias_semana')->nullable()->comment('JSON: Días de semana [0,1,2,3,4,5,6] donde 0=Domingo');
            $table->time('hora_desde')->nullable()->comment('Hora desde la cual aplica');
            $table->time('hora_hasta')->nullable()->comment('Hora hasta la cual aplica');

            // Límites de uso (opcional)
            $table->integer('usos_maximos')->nullable()->comment('Cantidad máxima de usos total');
            $table->integer('usos_por_cliente')->nullable()->comment('Usos máximos por cliente');
            $table->integer('usos_actuales')->default(0)->comment('Contador de usos actuales');

            $table->timestamps();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_promociones_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices
            $table->index(['sucursal_id', 'activo'], 'idx_sucursal_activo');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'idx_vigencia');
            $table->index(['prioridad', 'combinable'], 'idx_prioridad_combinable');
            $table->index('codigo_cupon', 'idx_codigo_cupon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('promociones');
    }
};
