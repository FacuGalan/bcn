<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Listas de Precios (Encabezado)
 *
 * Sistema de listas de precios flexible por sucursal.
 * Cada lista puede definir un porcentaje de ajuste global y condiciones de aplicación.
 *
 * CARACTERÍSTICAS:
 * - Una lista pertenece a una sucursal
 * - Puede tener un ajuste porcentual global (+ recargo, - descuento)
 * - Configuración de redondeo de precios
 * - Control sobre aplicación de promociones
 * - Vigencia temporal (fechas, días de semana, horarios)
 * - Condiciones de cantidad
 * - Lista base obligatoria por sucursal (no eliminable)
 * - Sistema de prioridades para resolver conflictos
 *
 * JERARQUÍA DE ESPECIFICIDAD:
 * 1. Lista con más condiciones cumplidas
 * 2. Mayor prioridad (menor número)
 * 3. Lista base como fallback
 *
 * FASE 2 - Sistema de Listas de Precios
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id')->comment('Sucursal a la que pertenece');
            $table->string('nombre', 255)->comment('Nombre de la lista');
            $table->string('codigo', 50)->nullable()->comment('Código único por sucursal');
            $table->text('descripcion')->nullable()->comment('Descripción detallada');

            // Ajuste por defecto
            $table->decimal('ajuste_porcentaje', 8, 2)->default(0)
                  ->comment('Porcentaje de ajuste global (+ recargo, - descuento)');

            // Redondeo
            $table->enum('redondeo', ['ninguno', 'entero', 'decena', 'centena'])
                  ->default('ninguno')
                  ->comment('Tipo de redondeo a aplicar en precios');

            // Control de promociones
            $table->boolean('aplica_promociones')->default(true)
                  ->comment('Si permite aplicar promociones');
            $table->enum('promociones_alcance', ['todos', 'solo_lista', 'ninguno'])
                  ->default('todos')
                  ->comment('Alcance: todos=toda venta, solo_lista=artículos de lista, ninguno=sin promos');

            // Vigencia temporal (como promociones)
            $table->date('vigencia_desde')->nullable()->comment('Fecha desde la cual aplica');
            $table->date('vigencia_hasta')->nullable()->comment('Fecha hasta la cual aplica');
            $table->text('dias_semana')->nullable()->comment('JSON: Días de semana [0-6] donde 0=Domingo');
            $table->time('hora_desde')->nullable()->comment('Hora desde la cual aplica');
            $table->time('hora_hasta')->nullable()->comment('Hora hasta la cual aplica');

            // Condiciones de cantidad
            $table->decimal('cantidad_minima', 12, 3)->nullable()
                  ->comment('Cantidad mínima para que aplique');
            $table->decimal('cantidad_maxima', 12, 3)->nullable()
                  ->comment('Cantidad máxima para que aplique');

            // Control
            $table->boolean('es_lista_base')->default(false)
                  ->comment('Si es la lista base obligatoria de la sucursal');
            $table->integer('prioridad')->default(100)
                  ->comment('Prioridad (menor número = mayor prioridad)');
            $table->boolean('activo')->default(true)->comment('Si está activa');

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (sin nombre explícito para permitir múltiples tenants)
            $table->foreign('sucursal_id')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('cascade');

            // Índices (sin nombres explícitos)
            $table->index(['sucursal_id', 'activo']);
            $table->index(['sucursal_id', 'es_lista_base']);
            $table->index(['vigencia_desde', 'vigencia_hasta']);
            $table->index('prioridad');

            // Código único por sucursal
            $table->unique(['sucursal_id', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('listas_precios');
    }
};
