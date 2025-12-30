<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla comprobantes_fiscales
 *
 * Registra todos los comprobantes fiscales emitidos (facturas, notas de crédito, etc.)
 * Un comprobante puede estar asociado a múltiples ventas (vía comprobante_fiscal_ventas)
 *
 * IMPORTANTE:
 * - Los comprobantes fiscales son documentos con validez ante AFIP
 * - Los tickets internos NO van en esta tabla (la venta misma es el ticket)
 * - El monto del comprobante puede ser diferente al total de la venta
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('comprobantes_fiscales', function (Blueprint $table) {
            $table->id();

            // Sucursal y punto de venta
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('punto_venta_id');
            $table->unsignedBigInteger('cuit_id')
                ->comment('CUIT emisor del comprobante');

            // Datos del comprobante
            $table->enum('tipo', [
                'factura_a', 'factura_b', 'factura_c', 'factura_e', 'factura_m',
                'nota_credito_a', 'nota_credito_b', 'nota_credito_c', 'nota_credito_e', 'nota_credito_m',
                'nota_debito_a', 'nota_debito_b', 'nota_debito_c', 'nota_debito_e', 'nota_debito_m',
                'recibo_a', 'recibo_b', 'recibo_c'
            ])->comment('Tipo de comprobante fiscal');

            $table->string('letra', 1)->comment('Letra del comprobante (A, B, C, E, M)');
            $table->unsignedInteger('punto_venta_numero')->comment('Número del punto de venta');
            $table->unsignedBigInteger('numero_comprobante')->comment('Número del comprobante');
            $table->string('cae', 20)->nullable()->comment('CAE otorgado por AFIP');
            $table->date('cae_vencimiento')->nullable()->comment('Fecha de vencimiento del CAE');

            // Fechas
            $table->date('fecha_emision');
            $table->date('fecha_servicio_desde')->nullable()
                ->comment('Fecha desde (para servicios)');
            $table->date('fecha_servicio_hasta')->nullable()
                ->comment('Fecha hasta (para servicios)');

            // Cliente/Receptor
            $table->unsignedBigInteger('cliente_id')->nullable()
                ->comment('Cliente asociado (puede ser diferente al de la venta)');
            $table->unsignedBigInteger('condicion_iva_id')
                ->comment('Condición de IVA del receptor (ref: config.condiciones_iva)');
            $table->string('receptor_nombre', 255)
                ->comment('Nombre/Razón social del receptor');
            $table->string('receptor_documento_tipo', 10)->default('CUIT')
                ->comment('Tipo de documento (CUIT, DNI, etc.)');
            $table->string('receptor_documento_numero', 20)
                ->comment('Número de documento del receptor');
            $table->string('receptor_domicilio', 500)->nullable();

            // Montos
            $table->decimal('neto_gravado', 12, 2)->default(0);
            $table->decimal('neto_no_gravado', 12, 2)->default(0);
            $table->decimal('neto_exento', 12, 2)->default(0);
            $table->decimal('iva_total', 12, 2)->default(0);
            $table->decimal('tributos', 12, 2)->default(0)
                ->comment('Otros tributos (percepciones, etc.)');
            $table->decimal('total', 12, 2);

            // Moneda
            $table->string('moneda', 3)->default('PES')
                ->comment('Código de moneda AFIP');
            $table->decimal('cotizacion', 12, 6)->default(1)
                ->comment('Cotización de la moneda');

            // Estado
            $table->enum('estado', ['pendiente', 'autorizado', 'rechazado', 'anulado'])
                ->default('pendiente')
                ->comment('Estado ante AFIP');

            // Respuesta AFIP
            $table->text('afip_response')->nullable()
                ->comment('Respuesta completa de AFIP (JSON)');
            $table->text('afip_observaciones')->nullable()
                ->comment('Observaciones de AFIP');
            $table->text('afip_errores')->nullable()
                ->comment('Errores de AFIP');

            // Comprobante asociado (para notas de crédito/débito)
            $table->unsignedBigInteger('comprobante_asociado_id')->nullable()
                ->comment('FK a comprobante original (para NC/ND)');

            // Auditoría
            $table->unsignedBigInteger('usuario_id')
                ->comment('Usuario que emitió el comprobante');
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_cf_sucursal')
                ->references('id')
                ->on('sucursales')
                ->onDelete('restrict');

            $table->foreign('punto_venta_id', 'fk_cf_punto_venta')
                ->references('id')
                ->on('puntos_venta')
                ->onDelete('restrict');

            $table->foreign('cuit_id', 'fk_cf_cuit')
                ->references('id')
                ->on('cuits')
                ->onDelete('restrict');

            $table->foreign('cliente_id', 'fk_cf_cliente')
                ->references('id')
                ->on('clientes')
                ->onDelete('set null');

            // Nota: condicion_iva_id referencia config.condiciones_iva (cross-database, sin FK)

            $table->foreign('comprobante_asociado_id', 'fk_cf_comprobante_asociado')
                ->references('id')
                ->on('comprobantes_fiscales')
                ->onDelete('set null');

            // Índices
            $table->unique(['punto_venta_id', 'tipo', 'numero_comprobante'], 'unique_cf_numero');
            $table->index(['sucursal_id', 'fecha_emision'], 'idx_cf_sucursal_fecha');
            $table->index('cliente_id', 'idx_cf_cliente');
            $table->index('cae', 'idx_cf_cae');
            $table->index('estado', 'idx_cf_estado');
            $table->index('tipo', 'idx_cf_tipo');
            $table->index('receptor_documento_numero', 'idx_cf_receptor_doc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('comprobantes_fiscales');
    }
};
