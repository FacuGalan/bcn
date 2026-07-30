<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T43 (ronda cuenta consumidor): trazabilidad INVERSA cliente→consumidor.
 * La única clave era unique(consumidor_id, comercio_id): buscar "¿qué cuenta
 * BCN es este cliente?" escaneaba la tabla. Con este índice el panel puede
 * mostrar el vínculo y unificar datos en ambos sentidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->table('consumidor_comercio', function (Blueprint $table) {
            $table->index(['comercio_id', 'cliente_id'], 'idx_cc_comercio_cliente');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('consumidor_comercio', function (Blueprint $table) {
            $table->dropIndex('idx_cc_comercio_cliente');
        });
    }
};
