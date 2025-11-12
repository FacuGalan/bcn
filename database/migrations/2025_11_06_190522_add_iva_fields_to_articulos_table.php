<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de IVA a Artículos
 *
 * Agrega la relación con tipos de IVA y configuración de precios con IVA.
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
        Schema::connection('pymes_tenant')->table('articulos', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_iva_id')->after('activo')
                  ->comment('Tipo de IVA del artículo');
            $table->boolean('precio_iva_incluido')->default(true)->after('tipo_iva_id')
                  ->comment('Si los precios incluyen IVA o no');

            // Foreign key
            $table->foreign('tipo_iva_id', 'fk_articulos_tipo_iva')
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
        Schema::connection('pymes_tenant')->table('articulos', function (Blueprint $table) {
            $table->dropForeign('fk_articulos_tipo_iva');
            $table->dropIndex('idx_tipo_iva');
            $table->dropColumn(['tipo_iva_id', 'precio_iva_incluido']);
        });
    }
};
