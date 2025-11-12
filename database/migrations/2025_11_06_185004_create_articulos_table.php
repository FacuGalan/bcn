<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Tabla Artículos
 *
 * Crea la tabla maestra de artículos compartidos entre sucursales.
 * Cada artículo es único en el comercio pero puede estar disponible
 * solo en algunas sucursales (ver tabla pivot articulos_sucursales).
 *
 * FASE 1 - Sistema Multi-Sucursal
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique()->comment('Código único del artículo en el comercio');
            $table->string('nombre', 255)->comment('Nombre del artículo');
            $table->text('descripcion')->nullable()->comment('Descripción detallada');
            $table->unsignedBigInteger('categoria_id')->nullable()->comment('Categoría del artículo');
            $table->unsignedBigInteger('marca_id')->nullable()->comment('Marca del artículo');
            $table->string('unidad_medida', 20)->default('unidad')->comment('Unidad de medida');
            $table->string('codigo_barra', 100)->nullable()->comment('Código de barras');
            $table->boolean('activo')->default(true)->comment('Si está activo en el catálogo');
            $table->timestamps();

            // Índices
            $table->index('codigo', 'idx_codigo');
            $table->index('activo', 'idx_activo');
            $table->index('categoria_id', 'idx_categoria');
            $table->index('marca_id', 'idx_marca');
        });

        // Índice parcial en nombre (primeros 191 caracteres para evitar error de longitud de clave)
        DB::connection('pymes_tenant')->statement(
            'ALTER TABLE `' . DB::connection('pymes_tenant')->getTablePrefix() . 'articulos`
             ADD INDEX idx_nombre (nombre(191))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('articulos');
    }
};
