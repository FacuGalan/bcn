<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T7 + RF-T6 (adelanto parcial) del spec tienda-online: IDs de analytics
 * por tienda (GA4 + Meta Pixel, Principio 11) y persistencia del tema
 * (design tokens JSON, Principio 10) en la tabla GLOBAL `tiendas` (BD config).
 *
 * `tema` nullable = la tienda usa los defaults del core (Tienda::TEMA_DEFAULTS);
 * el JSON persistido se mergea sobre los defaults en GET /tiendas/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: en las BD de test las columnas pueden existir sin el
        // registro en `migrations` (el centinela de TestCase corre migrate
        // dentro de la suite).
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            if (! Schema::connection('config')->hasColumn('tiendas', 'ga4_measurement_id')) {
                $table->string('ga4_measurement_id', 30)->nullable()->after('dominio_propio');
            }
            if (! Schema::connection('config')->hasColumn('tiendas', 'meta_pixel_id')) {
                $table->string('meta_pixel_id', 30)->nullable()->after('ga4_measurement_id');
            }
            if (! Schema::connection('config')->hasColumn('tiendas', 'tema')) {
                $table->json('tema')->nullable()->after('meta_pixel_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('tiendas', function (Blueprint $table) {
            $table->dropColumn(['ga4_measurement_id', 'meta_pixel_id', 'tema']);
        });
    }
};
