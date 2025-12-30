<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla pivot para formas de pago mixtas.
     * Define qué conceptos de pago acepta una forma de pago mixta.
     */
    public function up(): void
    {
        Schema::create('forma_pago_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forma_pago_id')
                ->constrained('formas_pago')
                ->cascadeOnDelete();
            $table->foreignId('concepto_pago_id')
                ->constrained('conceptos_pago')
                ->cascadeOnDelete();
            $table->timestamps();

            // Una forma de pago no puede tener el mismo concepto dos veces
            $table->unique(['forma_pago_id', 'concepto_pago_id'], 'forma_pago_concepto_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forma_pago_conceptos');
    }
};
