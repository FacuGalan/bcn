<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Configuración de Impresión por Sucursal
 *
 * Almacena la configuración general de impresión para cada sucursal.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('configuracion_impresion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->boolean('impresion_automatica_venta')->default(true)->comment('Imprimir ticket automaticamente');
            $table->boolean('impresion_automatica_factura')->default(true)->comment('Imprimir factura automaticamente');
            $table->boolean('abrir_cajon_efectivo')->default(true)->comment('Abrir cajon con pagos en efectivo');
            $table->boolean('cortar_papel_automatico')->default(true)->comment('Corte automatico en termicas');
            $table->string('logo_ticket_path', 255)->nullable()->comment('Ruta al logo para tickets');
            $table->text('texto_pie_ticket')->nullable()->comment('Texto al pie del ticket');
            $table->text('texto_legal_factura')->nullable()->comment('Texto legal para facturas');
            $table->timestamps();

            $table->unique('sucursal_id', 'uk_config_impresion_sucursal');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('configuracion_impresion');
    }
};
