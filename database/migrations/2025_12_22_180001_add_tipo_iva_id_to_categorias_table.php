<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar tipo_iva_id a categorías
 *
 * Permite asignar una alícuota de IVA por defecto a cada categoría.
 * Esto se usa para calcular el IVA de conceptos manuales que no tienen artículo
 * pero sí tienen categoría asignada.
 *
 * Si un artículo tiene tipo_iva_id propio, ese tiene prioridad.
 * Si un concepto tiene categoría con tipo_iva_id, se usa ese.
 * Si no tiene categoría o la categoría no tiene IVA, se usa 21% (código 5) por defecto.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('categorias', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_iva_id')->nullable()->after('activo')
                  ->comment('Tipo de IVA por defecto para conceptos de esta categoría');

            $table->foreign('tipo_iva_id')
                  ->references('id')
                  ->on('tipos_iva')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('categorias', function (Blueprint $table) {
            $table->dropForeign(['tipo_iva_id']);
            $table->dropColumn('tipo_iva_id');
        });
    }
};
