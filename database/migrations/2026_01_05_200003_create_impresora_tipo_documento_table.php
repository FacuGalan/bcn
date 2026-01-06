<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Tipos de Documento por Impresora
 *
 * Define qué tipos de documento imprime cada asignación impresora-sucursal-caja.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('impresora_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('impresora_sucursal_caja_id');
            $table->enum('tipo_documento', [
                'ticket_venta',
                'factura_a',
                'factura_b',
                'factura_c',
                'comanda',
                'precuenta',
                'cierre_turno',
                'cierre_caja',
                'arqueo',
                'recibo'
            ]);
            $table->unsignedTinyInteger('copias')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['impresora_sucursal_caja_id', 'tipo_documento'], 'uk_impresora_tipo_doc');
            $table->index('impresora_sucursal_caja_id', 'idx_itd_asignacion');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('impresora_tipo_documento');
    }
};
