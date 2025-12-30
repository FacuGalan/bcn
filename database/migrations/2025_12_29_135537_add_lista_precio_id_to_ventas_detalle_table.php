<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar lista_precio_id a ventas_detalle
 *
 * Permite registrar con qué lista de precios se calculó cada ítem vendido.
 * Esto es importante para trazabilidad y reportes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('lista_precio_id')->nullable()->after('tipo_iva_id')
                ->comment('Lista de precios usada para calcular el precio');

            $table->foreign('lista_precio_id', 'fk_vd_lista_precio')
                ->references('id')
                ->on('listas_precios')
                ->onDelete('set null');

            $table->index('lista_precio_id', 'idx_vd_lista_precio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->dropForeign('fk_vd_lista_precio');
            $table->dropIndex('idx_vd_lista_precio');
            $table->dropColumn('lista_precio_id');
        });
    }
};
