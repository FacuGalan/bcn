<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega el campo concepto_pago_id a formas_pago.
     * - Para formas de pago simples: concepto_pago_id apunta al concepto
     * - Para formas de pago mixtas: concepto_pago_id es NULL y es_mixta = true
     */
    public function up(): void
    {
        Schema::table('formas_pago', function (Blueprint $table) {
            // FK al concepto de pago (NULL para formas mixtas)
            $table->foreignId('concepto_pago_id')
                ->nullable()
                ->after('descripcion')
                ->constrained('conceptos_pago')
                ->nullOnDelete();

            // Indica si es una forma de pago mixta (acepta múltiples conceptos)
            $table->boolean('es_mixta')->default(false)->after('concepto_pago_id');

            $table->index('es_mixta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formas_pago', function (Blueprint $table) {
            $table->dropForeign(['concepto_pago_id']);
            $table->dropColumn(['concepto_pago_id', 'es_mixta']);
        });
    }
};
