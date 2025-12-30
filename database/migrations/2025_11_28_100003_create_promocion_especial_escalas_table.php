<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla permite definir escalas para promociones NxM.
     * Ejemplo: 2x1 normal, pero si llevas 6 es 6x4
     */
    public function up(): void
    {
        Schema::create('promocion_especial_escalas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_especial_id');

            // Rango de cantidad para aplicar esta escala
            $table->integer('cantidad_desde'); // Desde cuántas unidades aplica
            $table->integer('cantidad_hasta')->nullable(); // Hasta cuántas (null = sin límite)

            // NxM de esta escala
            $table->integer('lleva'); // N (cantidad que lleva)
            $table->integer('paga');  // M (cantidad que paga)

            $table->timestamps();

            // Índices
            $table->index('promocion_especial_id', 'promo_esp_escala_promo_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion_especial_escalas');
    }
};
