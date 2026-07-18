<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T11 del spec tienda-online: identidad visual de la tienda — logo y
 * portada (cover del header) en la tabla GLOBAL `tiendas` (BD config).
 *
 * Paths relativos del disco public (`tiendas/{comercio_id}/{uuid}.webp`,
 * re-encodeados por ImagenTiendaService); la API los expone como
 * `logo_url`/`portada_url` absolutas en GET /tiendas/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente por el mismo motivo que la migración de analytics/tema.
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            if (! Schema::connection('config')->hasColumn('tiendas', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('tema');
            }
            if (! Schema::connection('config')->hasColumn('tiendas', 'portada_path')) {
                $table->string('portada_path')->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'portada_path']);
        });
    }
};
