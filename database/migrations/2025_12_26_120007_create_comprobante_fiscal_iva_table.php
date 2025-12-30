<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla comprobante_fiscal_iva
 *
 * Registra el desglose de IVA por alícuota para cada comprobante fiscal.
 * AFIP requiere informar el IVA desagregado por alícuota.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('comprobante_fiscal_iva', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_fiscal_id');

            // Alícuota de IVA (código AFIP)
            $table->unsignedTinyInteger('codigo_afip')
                ->comment('Código AFIP: 3=0%, 4=10.5%, 5=21%, 6=27%, 8=5%, 9=2.5%');

            $table->decimal('alicuota', 5, 2)
                ->comment('Porcentaje de IVA');

            $table->decimal('base_imponible', 12, 2)
                ->comment('Base imponible para esta alícuota');

            $table->decimal('importe', 12, 2)
                ->comment('Importe de IVA');

            $table->timestamp('created_at')->useCurrent();

            // Foreign key
            $table->foreign('comprobante_fiscal_id', 'fk_cfi_comprobante')
                ->references('id')
                ->on('comprobantes_fiscales')
                ->onDelete('cascade');

            // Índice
            $table->index('comprobante_fiscal_id', 'idx_cfi_comprobante');

            // Un solo registro por alícuota por comprobante
            $table->unique(['comprobante_fiscal_id', 'codigo_afip'], 'unique_cfi_alicuota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('comprobante_fiscal_iva');
    }
};
