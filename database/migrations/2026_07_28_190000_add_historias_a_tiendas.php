<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T24 del spec tienda-historias-y-fixes-circuito: historias destacadas de
 * la tienda (hasta 3 fotos estilo Instagram) en la tabla GLOBAL `tiendas`
 * (BD config).
 *
 * JSON: [{ "id": "uuid", "path": "tiendas/{comercio_id}/historias/{uuid}.webp",
 * "orden": 1 }]. Máximo 3 (validado en ImagenTiendaService, no en BD). La API
 * las expone como `historias: [{id, url}]` en GET /tiendas/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente por el mismo motivo que logo/portada.
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            if (! Schema::connection('config')->hasColumn('tiendas', 'historias')) {
                $table->json('historias')->nullable()->after('portada_path');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            $table->dropColumn('historias');
        });
    }
};
