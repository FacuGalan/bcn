<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Campos de configuración para sucursales
 *
 * Agrega campos individuales para configurar el comportamiento
 * del sistema en cada sucursal.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
            // Autorización
            $table->boolean('usa_clave_autorizacion')->default(false)->comment('Si requiere clave para operaciones especiales');
            $table->string('clave_autorizacion', 255)->nullable()->comment('Clave/PIN de autorización');

            // Impresión de facturas
            $table->enum('tipo_impresion_factura', ['solo_datos', 'solo_logo', 'ambos'])
                  ->default('ambos')
                  ->comment('Tipo de impresión en facturas: solo_datos (fiscales), solo_logo, ambos');

            // Comandas
            $table->boolean('imprime_encabezado_comanda')->default(true)->comment('Si imprime encabezado en comandas');

            // Agrupación de artículos
            $table->boolean('agrupa_articulos_venta')->default(true)->comment('Si agrupa artículos al cargar detalle de venta');
            $table->boolean('agrupa_articulos_impresion')->default(true)->comment('Si agrupa artículos al imprimir');

            // WhatsApp
            $table->boolean('usa_whatsapp_escritorio')->default(false)->comment('Si usa WhatsApp desktop');
            $table->boolean('envia_whatsapp_comanda')->default(false)->comment('Si envía WhatsApp al comandar');
            $table->text('mensaje_whatsapp_comanda')->nullable()->comment('Mensaje adicional para WhatsApp al comandar');
            $table->boolean('envia_whatsapp_listo')->default(false)->comment('Si envía WhatsApp cuando pedido está listo/en camino');
            $table->text('mensaje_whatsapp_listo')->nullable()->comment('Mensaje adicional para WhatsApp pedido listo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('sucursales', function (Blueprint $table) {
            $table->dropColumn([
                'usa_clave_autorizacion',
                'clave_autorizacion',
                'tipo_impresion_factura',
                'imprime_encabezado_comanda',
                'agrupa_articulos_venta',
                'agrupa_articulos_impresion',
                'usa_whatsapp_escritorio',
                'envia_whatsapp_comanda',
                'mensaje_whatsapp_comanda',
                'envia_whatsapp_listo',
                'mensaje_whatsapp_listo',
            ]);
        });
    }
};
